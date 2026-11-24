<?php
declare(strict_types=1);

defined('ROOT') || (http_response_code(403) && exit);

/*
 * Import: CSV / TSV / XLSX upload, public Google Sheets link, or pasted table.
 * Flow: parse -> validate/normalize (preview, nothing written) -> confirm (single transaction).
 * Security: size/row limits, zip-bomb guard, no external XML entities, Google-only host allow-list for
 * link imports, every value length-capped and normalized, parameterized SQL only, CSRF on all POSTs.
 */

const IMPORT_MAX_ROWS = 1000;
const IMPORT_MAX_BYTES = 2 * 1024 * 1024;
const IMPORT_TTL = 1800;

/** Message safe to show to the user. */
final class ImportError extends RuntimeException
{
}

/** Dataset definitions: column key => [header, aliases (normalized), required, hint]. */
function import_datasets(): array
{
    $list = fn(array $a) => implode(', ', $a);
    $platforms = array_keys(platforms());
    $statusLabels = array_map(fn($s) => (string) $s['label'], statuses());
    return [
        'content' => [
            'label' => 'Content', 'icon' => 'calendar', 'sheet' => 'Content', 'url' => '/content',
            'desc' => 'Posts with schedule, status, caption and performance metrics.',
            'cols' => [
                'title'        => ['Title', ['title', 'judul', 'posttitle', 'contenttitle'], true, 'Post title (max 160 characters).'],
                'platform'     => ['Platform', ['platform', 'channel'], false, 'One of: ' . $list($platforms) . '.'],
                'format'       => ['Format', ['format', 'type', 'contentformat'], false, 'One of: ' . $list(formats()) . '.'],
                'pillar'       => ['Pillar', ['pillar', 'contentpillar', 'topic'], false, 'One of: ' . $list(pillars()) . '.'],
                'status'       => ['Status', ['status', 'stage'], false, 'One of: ' . $list($statusLabels) . '. Default: Draft.'],
                'campaign'     => ['Campaign', ['campaign', 'campaignname'], false, 'Campaign name. Created automatically if it does not exist (optional).'],
                'scheduled_at' => ['Scheduled At', ['scheduledat', 'scheduled', 'schedule', 'scheduleddate', 'jadwal', 'tanggal', 'date', 'publishdate'], false, 'Date and time, e.g. 2026-12-01 09:00 or 01/12/2026 09:00 (day/month/year). Time defaults to 09:00.'],
                'published_at' => ['Published At', ['publishedat', 'published', 'publisheddate', 'tanggalpublish'], false, 'Only used for Published items. Same date format.'],
                'progress'     => ['Progress %', ['progress', 'progresspercent'], false, 'Number 0–100 (Published is always 100).'],
                'views'        => ['Views', ['views', 'view', 'impressions'], false, 'Whole number.'],
                'likes'        => ['Likes', ['likes', 'like'], false, 'Whole number.'],
                'comments'     => ['Comments', ['comments', 'comment'], false, 'Whole number.'],
                'shares'       => ['Shares', ['shares', 'share'], false, 'Whole number.'],
                'saves'        => ['Saves', ['saves', 'save'], false, 'Whole number.'],
                'caption'      => ['Caption', ['caption', 'captionscript', 'script', 'deskripsi'], false, 'Caption / script (Markdown allowed, max 5000 characters).'],
                'hashtags'     => ['Hashtags', ['hashtags', 'hashtag', 'tags'], false, 'e.g. #marketing #tips (max 1000 characters).'],
                'post_url'     => ['Post URL', ['posturl', 'url', 'link', 'postlink'], false, 'Full link starting with http:// or https://.'],
            ],
        ],
        'campaigns' => [
            'label' => 'Campaigns', 'icon' => 'flag', 'sheet' => 'Campaigns', 'url' => '/campaigns',
            'desc' => 'Campaign name, goal, status and dates.',
            'cols' => [
                'name'       => ['Name', ['name', 'campaign', 'campaignname', 'nama'], true, 'Campaign name (max 120 characters).'],
                'goal'       => ['Goal', ['goal', 'objective', 'tujuan'], false, 'Goal / objective (Markdown allowed, max 2000 characters).'],
                'status'     => ['Status', ['status'], false, 'One of: ' . $list(array_map(fn($s) => $s['label'], CAMPAIGN_STATUSES)) . '. Default: Planned.'],
                'start_date' => ['Start Date', ['startdate', 'start', 'mulai'], false, 'Date, e.g. 2026-12-01 or 01/12/2026.'],
                'end_date'   => ['End Date', ['enddate', 'end', 'selesai'], false, 'Date, same format.'],
                'color'      => ['Color', ['color', 'colour', 'warna'], false, 'One of: ' . $list(array_keys(CAMPAIGN_COLORS)) . '. Default: blue.'],
            ],
        ],
        'ideas' => [
            'label' => 'Ideas', 'icon' => 'bulb', 'sheet' => 'Ideas', 'url' => '/ideas',
            'desc' => 'Idea Bank entries with pillar, priority and status.',
            'cols' => [
                'title'    => ['Idea', ['idea', 'title', 'judul'], true, 'The idea (max 200 characters).'],
                'notes'    => ['Notes', ['notes', 'note', 'outline', 'catatan'], false, 'Notes / outline (Markdown allowed, max 5000 characters).'],
                'pillar'   => ['Pillar', ['pillar', 'contentpillar', 'topic'], false, 'One of: ' . $list(pillars()) . '.'],
                'priority' => ['Priority', ['priority', 'prioritas'], false, 'Low, Medium or High. Default: Medium.'],
                'status'   => ['Status', ['status'], false, 'New, Saved or Used. Default: New.'],
            ],
        ],
    ];
}

/* ====================== Parsing: CSV / TSV / pasted text ====================== */

function import_norm_key(string $s): string
{
    return (string) preg_replace('/[^a-z0-9]+/', '', strtolower(trim($s)));
}

function import_decode(string $raw): string
{
    if (str_starts_with($raw, "\xEF\xBB\xBF")) {
        return substr($raw, 3);
    }
    if (str_starts_with($raw, "\xFF\xFE")) {
        return mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');
    }
    if (str_starts_with($raw, "\xFE\xFF")) {
        return mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
    }
    return mb_check_encoding($raw, 'UTF-8') ? $raw : mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
}

/** @return list<list<string>> */
function import_parse_delimited(string $raw): array
{
    if (strlen($raw) > IMPORT_MAX_BYTES) {
        throw new ImportError('The data is larger than 2 MB. Please split it into smaller files.');
    }
    $raw = import_decode($raw);
    if (str_contains($raw, "\0")) {
        throw new ImportError('This does not look like a text/CSV file. For Excel files use .xlsx.');
    }
    $line = substr($raw, 0, strcspn($raw, "\r\n"));
    $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
    arsort($counts);
    $delim = (string) array_key_first($counts);
    if ($counts["\t"] > 0 && $counts["\t"] >= $counts[$delim]) {
        $delim = "\t";
    }

    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $raw);
    rewind($fh);
    $rows = [];
    while (($r = fgetcsv($fh, 0, $delim, '"', '')) !== false) {
        if ($r === [null]) {
            continue;
        }
        $rows[] = array_map(static fn($v) => (string) $v, array_slice($r, 0, 60));
        if (count($rows) > IMPORT_MAX_ROWS + 20) {
            break;
        }
    }
    fclose($fh);
    return $rows;
}

/* ====================== Parsing: XLSX (ZipArchive + SimpleXML, no external libraries) ====================== */

function import_xml(?string $s): ?SimpleXMLElement
{
    if ($s === null || $s === '') {
        return null;
    }
    if (stripos($s, '<!DOCTYPE') !== false || stripos($s, '<!ENTITY') !== false) {
        throw new ImportError('The spreadsheet contains unsupported content.');
    }
    $prev = libxml_use_internal_errors(true);
    $x = simplexml_load_string($s, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    return $x ?: null;
}

function import_col_index(string $ref): int
{
    $n = 0;
    foreach (str_split(strtoupper((string) preg_replace('/[^A-Za-z]/', '', $ref))) as $ch) {
        $n = $n * 26 + (ord($ch) - 64);
    }
    return max(0, $n - 1);
}

function import_xlsx_date(float $serial): string
{
    $ts = (int) round(($serial - 25569) * 86400);
    return fmod($serial, 1.0) == 0.0 ? gmdate('Y-m-d', $ts) : gmdate('Y-m-d H:i', $ts);
}

/** @return list<list<string>> */
function import_parse_xlsx(string $path, string $preferSheet): array
{
    if (!class_exists(ZipArchive::class)) {
        throw new ImportError('XLSX import needs the PHP "zip" extension. Please upload a CSV file instead.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::RDONLY) !== true) {
        throw new ImportError('Could not open this file. Is it a valid .xlsx workbook?');
    }
    try {
        if ($zip->numFiles > 300) {
            throw new ImportError('This workbook has too many internal files.');
        }
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $st = $zip->statIndex($i);
            $total += (int) ($st['size'] ?? 0);
            if (($st['size'] ?? 0) > 30 * 1024 * 1024 || $total > 80 * 1024 * 1024) {
                throw new ImportError('This workbook is too large to import safely.');
            }
        }
        $get = static fn(string $n): ?string => ($s = $zip->getFromName($n)) === false ? null : $s;

        $wb = import_xml($get('xl/workbook.xml')) ?? throw new ImportError('This is not a valid .xlsx workbook.');
        $relNs = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $sheets = [];
        foreach ($wb->sheets->sheet ?? [] as $sh) {
            $sheets[] = ['name' => (string) $sh['name'], 'rid' => (string) $sh->attributes($relNs)['id']];
        }
        if (!$sheets) {
            throw new ImportError('The workbook has no sheets.');
        }
        $pick = $sheets[0];
        foreach ($sheets as $sh) {
            if (strcasecmp($sh['name'], $preferSheet) === 0) {
                $pick = $sh;
                break;
            }
        }
        $targets = [];
        $rels = import_xml($get('xl/_rels/workbook.xml.rels'));
        foreach ($rels->Relationship ?? [] as $rel) {
            $targets[(string) $rel['Id']] = (string) $rel['Target'];
        }
        $target = $targets[$pick['rid']] ?? 'worksheets/sheet1.xml';
        $target = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
        $sheetXml = import_xml($get($target)) ?? throw new ImportError('Could not read the first sheet of this workbook.');

        $shared = [];
        if (($ss = import_xml($get('xl/sharedStrings.xml'))) !== null) {
            foreach ($ss->si as $si) {
                $t = '';
                if (isset($si->t)) {
                    $t = (string) $si->t;
                } else {
                    foreach ($si->r as $run) {
                        $t .= (string) $run->t;
                    }
                }
                $shared[] = $t;
            }
        }

        // Which cell styles are dates?
        $dateStyle = [];
        if (($sty = import_xml($get('xl/styles.xml'))) !== null) {
            $custom = [];
            foreach ($sty->numFmts->numFmt ?? [] as $nf) {
                $custom[(int) $nf['numFmtId']] = (string) $nf['formatCode'];
            }
            $i = 0;
            foreach ($sty->cellXfs->xf ?? [] as $xf) {
                $id = (int) $xf['numFmtId'];
                $isDate = ($id >= 14 && $id <= 22) || ($id >= 27 && $id <= 36) || ($id >= 45 && $id <= 47) || ($id >= 50 && $id <= 58);
                if (!$isDate && isset($custom[$id])) {
                    $code = (string) preg_replace('/"[^"]*"|\[[^\]]*\]|\\\\./', '', $custom[$id]);
                    $isDate = (bool) preg_match('/[dmyhs]/i', $code);
                }
                $dateStyle[$i++] = $isDate;
            }
        }

        $rows = [];
        foreach ($sheetXml->sheetData->row ?? [] as $row) {
            $line = [];
            foreach ($row->c as $c) {
                $idx = isset($c['r']) ? import_col_index((string) $c['r']) : count($line);
                if ($idx > 59) {
                    continue;
                }
                $type = (string) $c['t'];
                $v = isset($c->v) ? (string) $c->v : '';
                $val = '';
                if ($type === 's') {
                    $val = $shared[(int) $v] ?? '';
                } elseif ($type === 'inlineStr') {
                    if (isset($c->is->t)) {
                        $val = (string) $c->is->t;
                    } else {
                        foreach ($c->is->r ?? [] as $run) {
                            $val .= (string) $run->t;
                        }
                    }
                } elseif ($type === 'b') {
                    $val = $v === '1' ? '1' : '0';
                } elseif ($type === 'e') {
                    $val = '';
                } elseif ($type === 'str') {
                    $val = $v;
                } elseif ($v !== '' && is_numeric($v)) {
                    $f = (float) $v;
                    if (!empty($dateStyle[(int) $c['s']])) {
                        $val = import_xlsx_date($f);
                    } else {
                        $val = (floor($f) == $f && abs($f) < 1e15) ? (string) (int) $f : rtrim(rtrim(sprintf('%.10F', $f), '0'), '.');
                    }
                } else {
                    $val = $v;
                }
                while (count($line) < $idx) {
                    $line[] = '';
                }
                $line[$idx] = $val;
            }
            if (array_filter($line, static fn($x) => trim((string) $x) !== '')) {
                $rows[] = $line;
            }
            if (count($rows) > IMPORT_MAX_ROWS + 20) {
                break;
            }
        }
        return $rows;
    } finally {
        $zip->close();
    }
}

/* ====================== Google Sheets (public link) ====================== */

function import_fetch_sheet(string $url): string
{
    $url = trim($url);
    if (!preg_match('#^https://docs\.google\.com/spreadsheets/d/([A-Za-z0-9_-]{20,})#', $url, $m)) {
        throw new ImportError('Please paste a full Google Sheets link, e.g. https://docs.google.com/spreadsheets/d/…/edit');
    }
    $target = 'https://docs.google.com/spreadsheets/d/' . $m[1] . '/export?format=csv';
    if (preg_match('/[#&?]gid=(\d{1,12})/', $url, $g)) {
        $target .= '&gid=' . $g[1];
    }
    if (!function_exists('curl_init')) {
        throw new ImportError('Google Sheets import needs the PHP "curl" extension. Download the sheet as CSV and upload it instead.');
    }

    $allowed = static function (string $u): bool {
        $p = parse_url($u);
        $h = strtolower((string) ($p['host'] ?? ''));
        return ($p['scheme'] ?? '') === 'https'
            && ($h === 'docs.google.com' || $h === 'drive.google.com' || str_ends_with($h, '.googleusercontent.com'));
    };

    for ($hop = 0; $hop < 5; $hop++) {
        $body = '';
        $headers = [];
        $ch = curl_init($target);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT      => 'ContentPlanner/' . APP_VERSION,
            CURLOPT_HTTPHEADER     => ['Accept: text/csv,*/*;q=0.5'],
            CURLOPT_WRITEFUNCTION  => static function ($c, string $d) use (&$body): int {
                $body .= $d;
                return strlen($body) > IMPORT_MAX_BYTES ? -1 : strlen($d); // abort oversized responses
            },
            CURLOPT_HEADERFUNCTION => static function ($c, string $h) use (&$headers): int {
                if (str_contains($h, ':')) {
                    [$k, $v] = explode(':', $h, 2);
                    $headers[strtolower(trim($k))] = trim($v);
                }
                return strlen($h);
            },
        ]);
        $ok = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($ch);

        if ($errno === CURLE_WRITE_ERROR) {
            throw new ImportError('The sheet is larger than 2 MB. Please import a smaller tab.');
        }
        if ($ok === false && $code === 0) {
            throw new ImportError('Could not reach Google Sheets from this server. Download the sheet as CSV/XLSX and upload it instead.');
        }
        if ($code >= 300 && $code < 400 && !empty($headers['location'])) {
            $loc = $headers['location'];
            if (str_starts_with($loc, '/')) {
                $loc = 'https://docs.google.com' . $loc;
            }
            if (!$allowed($loc)) {
                throw new ImportError('Google Sheets redirected to an unexpected address; import was blocked.');
            }
            $target = $loc;
            continue;
        }
        if ($code === 200) {
            if (str_contains(strtolower($headers['content-type'] ?? ''), 'text/html')) {
                throw new ImportError('The sheet is private. In Google Sheets click Share → General access → “Anyone with the link” (Viewer), or download it as CSV/XLSX and upload it here.');
            }
            return $body;
        }
        if (in_array($code, [401, 403], true)) {
            throw new ImportError('Access denied. Set the sheet to “Anyone with the link” (Viewer), or upload it as a file instead.');
        }
        if ($code === 404) {
            throw new ImportError('Sheet not found. Check the link.');
        }
        throw new ImportError('Google Sheets returned an unexpected response (HTTP ' . $code . ').');
    }
    throw new ImportError('Too many redirects from Google Sheets.');
}

/* ====================== Upload handling ====================== */

/** @return list<list<string>> */
function import_read_upload(string $dataset): array
{
    $f = $_FILES['file'] ?? null;
    if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new ImportError('Please choose a file to upload.');
    }
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || (int) $f['size'] > IMPORT_MAX_BYTES) {
        throw new ImportError('The file is larger than 2 MB. Please split it into smaller files.');
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
        throw new ImportError('The upload failed. Please try again.');
    }
    $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
    $tmp = (string) $f['tmp_name'];
    $head = (string) file_get_contents($tmp, false, null, 0, 4);

    if ($ext === 'xlsx' || ($ext === 'xlsm')) {
        if (!str_starts_with($head, 'PK')) {
            throw new ImportError('This file is not a valid .xlsx workbook.');
        }
        return import_parse_xlsx($tmp, (string) import_datasets()[$dataset]['sheet']);
    }
    if ($ext === 'xls') {
        throw new ImportError('Legacy .xls files are not supported. Save the file as .xlsx or .csv and try again.');
    }
    if (in_array($ext, ['csv', 'tsv', 'txt'], true)) {
        if (str_starts_with($head, 'PK')) {
            throw new ImportError('This looks like an Excel file. Rename it to .xlsx or export it as CSV.');
        }
        return import_parse_delimited((string) file_get_contents($tmp));
    }
    throw new ImportError('Unsupported file type. Use .csv, .tsv or .xlsx.');
}

/* ====================== Normalization ====================== */

function import_text(string $v, int $max, bool $multiline = false): string
{
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    $v = (string) preg_replace($multiline ? '/[^\P{C}\n\t]/u' : '/\p{C}/u', ' ', $v);
    if (preg_match('/^\'[=+\-@]/', $v)) {
        $v = substr($v, 1); // undo the formula-injection guard added by our own CSV export
    }
    return mb_substr(trim($v), 0, $max);
}

function import_int(string $v, array &$msgs, string $label): int
{
    $v = trim(str_replace([' ', '%'], '', $v));
    if ($v === '') {
        return 0;
    }
    if (preg_match('/^\d{1,3}([.,]\d{3})+$/', $v)) {
        $v = str_replace(['.', ','], '', $v);
    } elseif (preg_match('/^\d+[.,]\d+$/', $v)) {
        $v = str_replace(',', '.', $v);
    } elseif (preg_match('/^(\d+(?:[.,]\d+)?)\s*([kKmM])$/', $v, $m)) {
        $v = (string) ((float) str_replace(',', '.', $m[1]) * (strtolower($m[2]) === 'k' ? 1e3 : 1e6));
    }
    if (!is_numeric($v) || (float) $v < 0) {
        $msgs[] = "$label “" . mb_substr($v, 0, 20) . '” is not a valid number — set to 0';
        return 0;
    }
    return (int) min(2000000000, round((float) $v));
}

/** @return string|null Y-m-d or Y-m-d H:i, or null when empty/invalid ($bad = true when invalid). */
function import_date(string $v, bool $withTime, bool &$bad): ?string
{
    $bad = false;
    $v = trim($v);
    if ($v === '') {
        return null;
    }
    $y = $mo = $d = 0;
    $time = '';
    if (preg_match('/^\d{5}(\.\d+)?$/', $v) && (float) $v >= 20000 && (float) $v <= 80000) {
        $s = import_xlsx_date((float) $v);
        [$datePart, $time] = array_pad(explode(' ', $s, 2), 2, '');
        [$y, $mo, $d] = array_map('intval', explode('-', $datePart));
    } elseif (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})(?:[ T,]+(.+))?$/', $v, $m)) {
        [$d, $mo, $y, $time] = [(int) $m[1], (int) $m[2], (int) $m[3], $m[4] ?? ''];
    } elseif (preg_match('/^(\d{4})[\/.\-](\d{1,2})[\/.\-](\d{1,2})(?:[ T,]+(.+))?$/', $v, $m)) {
        [$y, $mo, $d, $time] = [(int) $m[1], (int) $m[2], (int) $m[3], $m[4] ?? ''];
    } else {
        $t = strtotime($v);
        if ($t === false) {
            $bad = true;
            return null;
        }
        [$y, $mo, $d] = [(int) date('Y', $t), (int) date('n', $t), (int) date('j', $t)];
        $time = str_contains($v, ':') ? date('H:i', $t) : '';
    }
    if (!checkdate($mo, $d, $y) || $y < 1990 || $y > 2100) {
        $bad = true;
        return null;
    }
    $date = sprintf('%04d-%02d-%02d', $y, $mo, $d);
    if (!$withTime) {
        return $date;
    }
    $h = 9;
    $i = 0;
    if (trim($time) !== '') {
        if (!preg_match('/^(\d{1,2})[:.](\d{2})(?::\d{2})?\s*(am|pm)?$/i', trim($time), $tm)) {
            $bad = true;
            return null;
        }
        $h = (int) $tm[1];
        $i = (int) $tm[2];
        if (isset($tm[3]) && $tm[3] !== '') {
            $h = $h % 12 + (strtolower($tm[3]) === 'pm' ? 12 : 0);
        }
        if ($h > 23 || $i > 59) {
            $bad = true;
            return null;
        }
    }
    return sprintf('%s %02d:%02d', $date, $h, $i);
}

/** Case-insensitive lookup of $v in a list of [key => label] choices. */
function import_pick(string $v, array $choices): ?string
{
    $n = import_norm_key($v);
    foreach ($choices as $key => $label) {
        if ($n === import_norm_key((string) $key) || $n === import_norm_key((string) $label)) {
            return (string) $key;
        }
    }
    return null;
}

/** @return array{0: string, 1: array, 2: list<string>, 3: list<string>} status, data, warnings, errors */
function import_normalize(string $ds, array $raw): array
{
    $warn = [];
    $err = [];
    $bad = false;
    $d = [];

    if ($ds === 'content') {
        $d['title'] = import_text($raw['title'] ?? '', 160);
        if ($d['title'] === '') {
            $err[] = 'Title is empty';
        }
        $plats = platforms();
        $v = import_text($raw['platform'] ?? '', 60);
        $key = $v === '' ? null : import_pick($v, array_combine(array_keys($plats), array_keys($plats)));
        if ($v !== '' && $key === null) {
            $warn[] = "Unknown platform “{$v}” — using " . array_key_first($plats) . '. Add it under Labels first to keep it';
        }
        $d['platform'] = $key ?? (string) array_key_first($plats);

        $fm = formats();
        $v = import_text($raw['format'] ?? '', 60);
        $key = $v === '' ? null : import_pick($v, array_combine($fm, $fm));
        if ($v !== '' && $key === null) {
            $warn[] = "Unknown format “{$v}” — using " . ($fm[0] ?? 'Feed');
        }
        $d['format'] = $key ?? ($fm[0] ?? 'Feed');

        $pl = pillars();
        $v = import_text($raw['pillar'] ?? '', 60);
        $key = $v === '' ? null : import_pick($v, array_combine($pl, $pl));
        if ($v !== '' && $key === null) {
            $warn[] = "Unknown pillar “{$v}” — left empty";
        }
        $d['pillar'] = $key ?? '';

        $sts = statuses();
        $labels = array_map(fn($s) => (string) $s['label'], $sts);
        $v = import_text($raw['status'] ?? '', 40);
        $key = $v === '' ? null : import_pick($v, $labels);
        $def = isset($sts['draft']) ? 'draft' : (string) array_key_first($sts);
        if ($v !== '' && $key === null) {
            $warn[] = "Unknown status “{$v}” — using " . ($labels[$def] ?? $def);
        }
        $d['status'] = $key ?? $def;

        $d['campaign'] = import_text($raw['campaign'] ?? '', 120);
        foreach (['scheduled_at' => 'Scheduled At', 'published_at' => 'Published At'] as $k => $lbl) {
            $d[$k] = import_date($raw[$k] ?? '', true, $bad);
            if ($bad) {
                $warn[] = "$lbl “" . mb_substr((string) $raw[$k], 0, 25) . '” is not a valid date — ignored';
            }
        }
        $d['progress'] = $d['status'] === 'published' ? 100 : clamp(import_int($raw['progress'] ?? '', $warn, 'Progress'), 0, 100);
        foreach (['views', 'likes', 'comments', 'shares', 'saves'] as $k) {
            $d[$k] = import_int($raw[$k] ?? '', $warn, ucfirst($k));
        }
        $d['caption'] = import_text($raw['caption'] ?? '', 5000, true);
        $d['hashtags'] = import_text($raw['hashtags'] ?? '', 1000);
        $u = import_text($raw['post_url'] ?? '', 500);
        if ($u !== '' && !(preg_match('#^https?://#i', $u) && filter_var($u, FILTER_VALIDATE_URL))) {
            $warn[] = 'Post URL is not a valid http(s) link — ignored';
            $u = '';
        }
        $d['post_url'] = $u;
    } elseif ($ds === 'campaigns') {
        $d['name'] = import_text($raw['name'] ?? '', 120);
        if ($d['name'] === '') {
            $err[] = 'Name is empty';
        }
        $d['goal'] = import_text($raw['goal'] ?? '', 2000, true);
        $v = import_text($raw['status'] ?? '', 40);
        $labels = array_map(fn($s) => $s['label'], CAMPAIGN_STATUSES);
        $key = $v === '' ? null : (import_pick($v, $labels) ?? (import_norm_key($v) === 'completed' ? 'done' : null));
        if ($v !== '' && $key === null) {
            $warn[] = "Unknown status “{$v}” — using Planned";
        }
        $d['status'] = $key ?? 'planned';
        foreach (['start_date' => 'Start Date', 'end_date' => 'End Date'] as $k => $lbl) {
            $d[$k] = import_date($raw[$k] ?? '', false, $bad);
            if ($bad) {
                $warn[] = "$lbl “" . mb_substr((string) $raw[$k], 0, 25) . '” is not a valid date — ignored';
            }
        }
        if ($d['start_date'] && $d['end_date'] && $d['end_date'] < $d['start_date']) {
            $warn[] = 'End Date is before Start Date';
        }
        $c = strtolower(import_text($raw['color'] ?? '', 20));
        $d['color'] = isset(CAMPAIGN_COLORS[$c]) ? $c : 'blue';
    } else { // ideas
        $d['title'] = import_text($raw['title'] ?? '', 200);
        if ($d['title'] === '') {
            $err[] = 'Idea is empty';
        }
        $d['notes'] = import_text($raw['notes'] ?? '', 5000, true);
        $pl = pillars();
        $v = import_text($raw['pillar'] ?? '', 60);
        $key = $v === '' ? null : import_pick($v, array_combine($pl, $pl));
        if ($v !== '' && $key === null) {
            $warn[] = "Unknown pillar “{$v}” — left empty";
        }
        $d['pillar'] = $key ?? '';
        $v = import_text($raw['priority'] ?? '', 20);
        $alias = ['rendah' => 'low', 'sedang' => 'medium', 'normal' => 'medium', 'tinggi' => 'high'];
        $key = $v === '' ? null : (import_pick($v, PRIORITIES) ?? ($alias[import_norm_key($v)] ?? null));
        if ($v !== '' && $key === null) {
            $warn[] = "Unknown priority “{$v}” — using Medium";
        }
        $d['priority'] = $key ?? 'medium';
        $v = import_text($raw['status'] ?? '', 20);
        $key = $v === '' ? null : import_pick($v, ['new' => 'New', 'saved' => 'Saved', 'used' => 'Used']);
        if ($v !== '' && $key === null) {
            $warn[] = "Unknown status “{$v}” — using New";
        }
        $d['status'] = $key ?? 'new';
    }
    return [$err ? 'error' : ($warn ? 'warn' : 'ok'), $d, $warn, $err];
}

/** Dedupe key for a normalized record. */
function import_key(string $ds, array $d): string
{
    return match ($ds) {
        'content'   => mb_strtolower($d['title']) . '|' . mb_strtolower($d['platform']) . '|' . substr((string) $d['scheduled_at'], 0, 10),
        'campaigns' => mb_strtolower($d['name']),
        default     => mb_strtolower($d['title']),
    };
}

/** @return array<string,true> */
function import_existing_keys(string $ds): array
{
    $keys = [];
    if ($ds === 'content') {
        foreach (q('SELECT title, platform, scheduled_at FROM contents') as $r) {
            $keys[import_key($ds, $r)] = true;
        }
    } elseif ($ds === 'campaigns') {
        foreach (q('SELECT name FROM campaigns') as $r) {
            $keys[import_key($ds, $r)] = true;
        }
    } else {
        foreach (q('SELECT title FROM ideas') as $r) {
            $keys[import_key($ds, $r)] = true;
        }
    }
    return $keys;
}

/** Map header row + data rows to normalized records. @return list<array> */
function import_map(string $ds, array $rows): array
{
    $cols = import_datasets()[$ds]['cols'];
    $headerAt = null;
    foreach ($rows as $i => $r) {
        if (array_filter($r, static fn($c) => trim($c) !== '')) {
            $headerAt = $i;
            break;
        }
    }
    if ($headerAt === null) {
        throw new ImportError('The file is empty.');
    }
    $map = [];
    foreach ($rows[$headerAt] as $ci => $h) {
        $n = import_norm_key($h);
        foreach ($cols as $key => [$head, $aliases]) {
            if (in_array($key, $map, true)) {
                continue;
            }
            if ($n !== '' && in_array($n, $aliases, true)) {
                $map[$ci] = $key;
                break;
            }
        }
    }
    foreach ($cols as $key => [$head, , $required]) {
        if ($required && !in_array($key, $map, true)) {
            throw new ImportError("Could not find the “{$head}” column in the first row. Download the template to see the expected columns.");
        }
    }
    $records = [];
    $existing = import_existing_keys($ds);
    $seen = [];
    foreach (array_slice($rows, $headerAt + 1, null, true) as $i => $r) {
        $raw = [];
        foreach ($map as $ci => $key) {
            $raw[$key] = (string) ($r[$ci] ?? '');
        }
        if (!array_filter($raw, static fn($c) => trim($c) !== '')) {
            continue;
        }
        if (count($records) >= IMPORT_MAX_ROWS) {
            throw new ImportError('Too many rows. The limit is ' . IMPORT_MAX_ROWS . ' rows per import — please split the file.');
        }
        [$status, $data, $warn, $err] = import_normalize($ds, $raw);
        $dup = false;
        if ($status !== 'error') {
            $k = import_key($ds, $data);
            $dup = isset($existing[$k]) || isset($seen[$k]);
            $seen[$k] = true;
        }
        $records[] = ['n' => $i + 1, 'status' => $status, 'data' => $data, 'warn' => $warn, 'err' => $err, 'dup' => $dup];
    }
    if (!$records) {
        throw new ImportError('No data rows found below the header row.');
    }
    return $records;
}

/* ====================== Templates ====================== */

function import_template(string $ds): array
{
    $def = import_datasets()[$ds];
    $heads = array_map(fn($c) => $c[0], array_values($def['cols']));
    $day = fn(int $n) => date('Y-m-d', strtotime("+$n days"));
    $plats = array_keys(platforms());
    $fm = formats();
    $pl = pillars();
    $st = array_values(array_map(fn($s) => (string) $s['label'], statuses()));
    $stPub = (string) (statuses()['published']['label'] ?? end($st));
    $p = fn(int $i) => $plats[$i % count($plats)];
    $f = fn(int $i) => $fm[$i % count($fm)];
    $l = fn(int $i) => $pl[$i % count($pl)] ?? '';

    $examples = match ($ds) {
        'content' => [
            ['5 tips to plan a week of content', $p(0), $f(1), $l(0), $st[0] ?? 'Draft', 'Launch Week', $day(3) . ' 09:00', '', 0, 0, 0, 0, 0, 0, "Plan once, post all week.\n\n- Pick 3 themes\n- Batch your captions", '#contentplanning #socialmedia', ''],
            ['Behind the scenes: studio tour', $p(1), $f(2), $l(4), $st[1] ?? 'Review', 'Launch Week', $day(5) . ' 18:30', '', 40, 0, 0, 0, 0, 0, 'A quick look at how we work.', '#bts', ''],
            ['Customer story: how Sari doubled reach', $p(2), $f(0), $l(1), $stPub, 'Always-on', $day(-7) . ' 12:00', $day(-7) . ' 12:05', 100, 12400, 830, 64, 120, 210, 'Real results from a real customer.', '#success #casestudy', 'https://example.com/post/123'],
        ],
        'campaigns' => [
            ['Launch Week', 'Announce the new product and drive 500 sign-ups.', 'Active', $day(0), $day(14), 'blue'],
            ['Ramadan Series', 'Daily value posts during the month.', 'Planned', $day(30), $day(60), 'green'],
        ],
        default => [
            ['Myth vs fact: 3 scheduling mistakes', 'Short carousel. Hook: "Stop doing this".', $l(0), 'High', 'New'],
            ['Team Q&A reel', 'Collect questions from followers first.', $l(3), 'Medium', 'Saved'],
        ],
    };

    $guide = [['Column', 'Required', 'Accepted values']];
    foreach ($def['cols'] as [$h, , $req, $hint]) {
        $guide[] = [$h, $req ? 'Yes' : 'No', $hint];
    }
    $guide[] = ['', '', ''];
    $guide[] = ['Tips', '', 'Keep the header row (row 1). Column order does not matter and unknown columns are ignored.'];
    $guide[] = ['', '', 'Delete the example rows before importing your own data. Maximum ' . IMPORT_MAX_ROWS . ' rows and 2 MB per import.'];
    $guide[] = ['', '', 'Files exported from this app (Export page) can be imported again as-is.'];

    $lists = [];
    $colNames = array_keys($def['cols']);
    $choices = match ($ds) {
        'content'   => ['platform' => $plats, 'format' => $fm, 'pillar' => $pl, 'status' => $st],
        'campaigns' => ['status' => array_map(fn($s) => $s['label'], array_values(CAMPAIGN_STATUSES)), 'color' => array_keys(CAMPAIGN_COLORS)],
        default     => ['pillar' => $pl, 'priority' => ['Low', 'Medium', 'High'], 'status' => ['New', 'Saved', 'Used']],
    };
    foreach ($choices as $key => $vals) {
        $idx = array_search($key, $colNames, true);
        $vals = array_values(array_filter($vals, fn($v) => !str_contains((string) $v, ',') && !str_contains((string) $v, '"')));
        if ($idx !== false && $vals && strlen(implode(',', $vals)) <= 250) {
            $lists[(int) $idx] = $vals;
        }
    }
    return ['head' => $heads, 'rows' => $examples, 'guide' => $guide, 'lists' => $lists, 'sheet' => $def['sheet']];
}

function import_xml_text(string $s): string
{
    return htmlspecialchars((string) preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s), ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function import_col_name(int $i): string
{
    $s = '';
    for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
        $s = chr(65 + ($i - 1) % 26) . $s;
    }
    return $s;
}

/** Minimal, valid .xlsx writer (inline strings, header style, frozen header row, dropdown lists). */
function import_xlsx_build(array $sheets): string
{
    $zip = new ZipArchive();
    $tmp = tempnam(sys_get_temp_dir(), 'cpx');
    if ($tmp === false || $zip->open($tmp, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
        throw new RuntimeException('Cannot create workbook.');
    }
    $types = '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    $wbSheets = '';
    $wbRels = '';
    $n = 0;
    foreach ($sheets as $name => $sh) {
        $n++;
        $types .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $wbSheets .= '<sheet name="' . import_xml_text((string) $name) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
        $wbRels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';

        $cols = '';
        foreach ($sh['widths'] as $i => $w) {
            $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        }
        $rowsXml = '';
        foreach ($sh['rows'] as $ri => $row) {
            $rowsXml .= '<row r="' . ($ri + 1) . '">';
            foreach (array_values($row) as $ci => $v) {
                $ref = import_col_name($ci) . ($ri + 1);
                $style = $ri === 0 && $sh['header'] ? ' s="1"' : (is_string($v) && str_contains($v, "\n") ? ' s="2"' : '');
                if (is_int($v) || is_float($v)) {
                    $rowsXml .= '<c r="' . $ref . '"' . $style . '><v>' . $v . '</v></c>';
                } elseif ((string) $v !== '') {
                    $rowsXml .= '<c r="' . $ref . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . import_xml_text((string) $v) . '</t></is></c>';
                }
            }
            $rowsXml .= '</row>';
        }
        $dv = '';
        foreach ($sh['lists'] ?? [] as $ci => $vals) {
            $c = import_col_name((int) $ci);
            $dv .= '<dataValidation type="list" allowBlank="1" showInputMessage="1" sqref="' . $c . '2:' . $c . '1001"><formula1>"' . import_xml_text(implode(',', $vals)) . '"</formula1></dataValidation>';
        }
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"' . ($n === 1 ? ' tabSelected="1"' : '') . '>'
            . ($sh['header'] ? '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>' : '') . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/><cols>' . $cols . '</cols><sheetData>' . $rowsXml . '</sheetData>'
            . ($dv !== '' ? '<dataValidations count="' . count($sh['lists']) . '">' . $dv . '</dataValidations>' : '') . '</worksheet>';
        $zip->addFromString('xl/worksheets/sheet' . $n . '.xml', $xml);
    }
    $wbRels .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>' . $types . '</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $wbSheets . '</sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $wbRels . '</Relationships>');
    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
        . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf></cellXfs></styleSheet>');
    $zip->close();
    $bin = (string) file_get_contents($tmp);
    @unlink($tmp);
    return $bin;
}

function action_import_template(string $ds, string $ext): void
{
    $t = import_template($ds);
    $file = 'content-planner-import-template-' . $ds . '.' . $ext;
    header('Cache-Control: private, no-store');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    if ($ext === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $t['head'], ',', '"', '');
        foreach ($t['rows'] as $r) {
            fputcsv($out, $r, ',', '"', '');
        }
        fclose($out);
        return;
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $widths = array_map(fn($h) => in_array($h, ['Caption', 'Notes', 'Goal'], true) ? 46 : (in_array($h, ['Title', 'Idea', 'Name', 'Post URL', 'Hashtags'], true) ? 36 : 16), $t['head']);
    echo import_xlsx_build([
        $t['sheet'] => ['rows' => [$t['head'], ...$t['rows']], 'widths' => $widths, 'header' => true, 'lists' => $t['lists']],
        'Guide'     => ['rows' => $t['guide'], 'widths' => [18, 10, 110], 'header' => true, 'lists' => []],
    ]);
}

/* ====================== Pages & actions ====================== */

function import_back(string $ds = ''): never
{
    redirect('/import' . (isset(import_datasets()[$ds]) ? '?ds=' . $ds : ''));
}

function action_import_preview(): void
{
    $ds = input('dataset');
    if (!isset(import_datasets()[$ds])) {
        $ds = 'content';
    }
    $source = input('source');
    try {
        $rows = match ($source) {
            'sheet' => import_parse_delimited(import_fetch_sheet(input('sheet_url'))),
            'paste' => import_parse_delimited(input('paste')),
            default => import_read_upload($ds),
        };
        $records = import_map($ds, $rows);
    } catch (ImportError $ex) {
        flash($ex->getMessage(), 'err');
        import_back($ds);
    } catch (Throwable $ex) {
        error_log('[content-planner] import: ' . $ex);
        flash('Could not read this data. Check the file format and try again.', 'err');
        import_back($ds);
    }
    $_SESSION['import'] = ['ds' => $ds, 'records' => $records, 'at' => time(), 'source' => $source];
    import_render_preview($ds, $records);
}

function import_render_preview(string $ds, array $records): void
{
    $def = import_datasets()[$ds];
    $count = ['ok' => 0, 'warn' => 0, 'error' => 0, 'dup' => 0];
    foreach ($records as $r) {
        $count[$r['status']]++;
        $count['dup'] += $r['dup'] ? 1 : 0;
    }
    $importable = $count['ok'] + $count['warn'];
    $cols = match ($ds) {
        'content'   => ['title' => 'Title', 'platform' => 'Platform', 'status' => 'Status', 'scheduled_at' => 'Scheduled'],
        'campaigns' => ['name' => 'Name', 'status' => 'Status', 'start_date' => 'Start', 'end_date' => 'End'],
        default     => ['title' => 'Idea', 'pillar' => 'Pillar', 'priority' => 'Priority', 'status' => 'Status'],
    };
    $shown = array_slice($records, 0, 100);

    ob_start(); ?>
    <div class="imp-wrap">
        <section class="card imp-card">
            <h2 class="imp-h"><?= e($def['label']) ?> import — review before saving</h2>
            <p class="imp-p">Nothing has been saved yet. Check the preview, then confirm.</p>
            <div class="imp-stats">
                <div><b><?= count($records) ?></b><span>Rows found</span></div>
                <div class="imp-good"><b><?= $importable ?></b><span>Ready to import</span></div>
                <div class="imp-warn"><b><?= $count['warn'] ?></b><span>With warnings</span></div>
                <div class="imp-bad"><b><?= $count['error'] ?></b><span>Will be skipped (errors)</span></div>
                <div><b><?= $count['dup'] ?></b><span>Already exist</span></div>
            </div>
        </section>

        <section class="card imp-card">
            <div class="imp-table-wrap">
                <table class="imp-table">
                    <thead><tr><th>Row</th><th>Result</th><?php foreach ($cols as $label): ?><th><?= e($label) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php foreach ($shown as $r): ?>
                        <tr>
                            <td><?= (int) $r['n'] ?></td>
                            <td>
                                <?php if ($r['status'] === 'error'): ?><span class="chip bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300">Error</span>
                                <?php elseif ($r['dup']): ?><span class="chip bg-slate-200 text-slate-700 dark:bg-slate-500/25 dark:text-slate-200">Duplicate</span>
                                <?php elseif ($r['status'] === 'warn'): ?><span class="chip bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">Warning</span>
                                <?php else: ?><span class="chip bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">OK</span><?php endif; ?>
                                <?php foreach (array_merge($r['err'], $r['warn']) as $m): ?><div class="imp-msg"><?= e($m) ?></div><?php endforeach; ?>
                            </td>
                            <?php foreach ($cols as $k => $label):
                                $val = (string) ($r['data'][$k] ?? '');
                                if ($k === 'status' && $ds === 'content') {
                                    $val = (string) (statuses()[$val]['label'] ?? $val);
                                }
                                ?><td><?= e(mb_strimwidth($val, 0, 70, '…')) ?></td><?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($records) > count($shown)): ?><p class="imp-p">…and <?= count($records) - count($shown) ?> more rows (all are imported).</p><?php endif; ?>
        </section>

        <form method="post" action="<?= e(url('/import/confirm')) ?>" class="card imp-card">
            <?= csrf_field() ?>
            <label class="imp-check"><input type="checkbox" name="skip_duplicates" value="1" checked> Skip rows that already exist
                <small><?= $ds === 'content' ? '(same title + platform + date)' : '(same ' . ($ds === 'campaigns' ? 'name' : 'idea text') . ')' ?></small></label>
            <?php if ($ds === 'content'): ?>
                <label class="imp-check"><input type="checkbox" name="create_campaigns" value="1" checked> Create campaigns that don’t exist yet</label>
            <?php endif; ?>
            <div class="imp-actions">
                <a href="<?= e(url('/import?ds=' . $ds)) ?>" class="btn-ghost">Cancel</a>
                <button type="submit" class="btn-primary" <?= $importable === 0 ? 'disabled' : '' ?>><?= icon('check', 'size-4') ?>Import <?= (int) $importable ?> <?= $importable === 1 ? 'row' : 'rows' ?></button>
            </div>
        </form>
    </div>
    <?php
    page('Import · Review', 'import', (string) ob_get_clean(), ['subtitle' => 'Review before saving']);
}

function action_import_confirm(): void
{
    $st = $_SESSION['import'] ?? null;
    if (!is_array($st) || (time() - (int) ($st['at'] ?? 0)) > IMPORT_TTL || !isset(import_datasets()[$st['ds'] ?? ''])) {
        unset($_SESSION['import']);
        flash('The import session expired. Please upload the data again.', 'err');
        redirect('/import');
    }
    $ds = (string) $st['ds'];
    $skipDup = input('skip_duplicates') === '1';
    $mkCampaigns = input('create_campaigns') === '1';
    $existing = $skipDup ? import_existing_keys($ds) : [];
    $added = 0;
    $skipped = 0;
    $failed = 0;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $campaigns = [];
        if ($ds === 'content') {
            foreach (q('SELECT id, name FROM campaigns') as $c) {
                $campaigns[mb_strtolower($c['name'])] = (int) $c['id'];
            }
        }
        $colors = array_keys(CAMPAIGN_COLORS);
        foreach ($st['records'] as $r) {
            if ($r['status'] === 'error') {
                $failed++;
                continue;
            }
            $d = $r['data'];
            if ($skipDup) {
                $k = import_key($ds, $d);
                if (isset($existing[$k])) {
                    $skipped++;
                    continue;
                }
                $existing[$k] = true;
            }
            if ($ds === 'content') {
                $cid = null;
                $cn = mb_strtolower($d['campaign']);
                if ($cn !== '') {
                    if (isset($campaigns[$cn])) {
                        $cid = $campaigns[$cn];
                    } elseif ($mkCampaigns) {
                        exec_sql('INSERT INTO campaigns(name,goal,status,color) VALUES(?,?,?,?)', [$d['campaign'], '', 'planned', $colors[count($campaigns) % count($colors)]]);
                        $cid = (int) $pdo->lastInsertId();
                        $campaigns[$cn] = $cid;
                    }
                }
                $pub = null;
                if ($d['status'] === 'published') {
                    $pub = $d['published_at'] ?: (($d['scheduled_at'] && $d['scheduled_at'] <= now_str()) ? $d['scheduled_at'] : now_str());
                }
                exec_sql('INSERT INTO contents(title,platform,format,pillar,status,campaign_id,scheduled_at,published_at,progress,caption,hashtags,post_url,views,likes,comments,shares,saves) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$d['title'], $d['platform'], $d['format'], $d['pillar'], $d['status'], $cid, $d['scheduled_at'], $pub, $d['progress'], $d['caption'], $d['hashtags'], $d['post_url'], $d['views'], $d['likes'], $d['comments'], $d['shares'], $d['saves']]);
            } elseif ($ds === 'campaigns') {
                exec_sql('INSERT INTO campaigns(name,goal,status,start_date,end_date,color) VALUES(?,?,?,?,?,?)', [$d['name'], $d['goal'], $d['status'], $d['start_date'], $d['end_date'], $d['color']]);
            } else {
                exec_sql('INSERT INTO ideas(title,notes,pillar,priority,status) VALUES(?,?,?,?,?)', [$d['title'], $d['notes'], $d['pillar'], $d['priority'], $d['status']]);
            }
            $added++;
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[content-planner] import commit: ' . $ex);
        flash('Import failed and no data was changed. Please try again.', 'err');
        redirect('/import?ds=' . $ds);
    }
    unset($_SESSION['import']);
    $msg = "Imported $added " . ($added === 1 ? 'row' : 'rows') . '.';
    $msg .= $skipped ? " Skipped $skipped duplicate" . ($skipped === 1 ? '.' : 's.') : '';
    $msg .= $failed ? " $failed row" . ($failed === 1 ? '' : 's') . ' had errors.' : '';
    flash($msg, $added ? 'ok' : 'err');
    redirect(import_datasets()[$ds]['url']);
}

function page_import(): void
{
    $defs = import_datasets();
    $ds = input('ds');
    if (!isset($defs[$ds])) {
        $ds = 'content';
    }
    ob_start(); ?>
    <div class="imp-wrap" x-data='{ ds: <?= e(json_encode($ds)) ?>, tab: "file", fileName: "" }'>
        <form method="post" enctype="multipart/form-data" action="<?= e(url('/import/preview')) ?>" class="card imp-card" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="dataset" :value="ds">
            <input type="hidden" name="source" :value="tab">
            <h2 class="imp-h">Import data</h2>
            <p class="imp-p">Bring in content, campaigns or ideas from a spreadsheet. You will see a preview before anything is saved.</p>

            <div class="imp-label">1. What are you importing?</div>
            <div class="imp-pills" role="radiogroup" aria-label="Dataset">
                <?php foreach ($defs as $k => $d): ?>
                    <button type="button" role="radio" class="pill" :aria-checked="ds === '<?= $k ?>'" :aria-current="ds === '<?= $k ?>'" @click="ds = '<?= $k ?>'"><?= icon($d['icon'], 'size-4') ?><?= e($d['label']) ?></button>
                <?php endforeach; ?>
            </div>

            <div class="imp-label">2. Choose a source</div>
            <div class="imp-pills" role="tablist">
                <button type="button" role="tab" class="pill" :aria-current="tab === 'file'" :aria-selected="tab === 'file'" @click="tab = 'file'">CSV / Excel file</button>
                <button type="button" role="tab" class="pill" :aria-current="tab === 'sheet'" :aria-selected="tab === 'sheet'" @click="tab = 'sheet'">Google Sheets link</button>
                <button type="button" role="tab" class="pill" :aria-current="tab === 'paste'" :aria-selected="tab === 'paste'" @click="tab = 'paste'">Paste table</button>
            </div>

            <div x-show="tab === 'file'" class="imp-panel">
                <label class="imp-drop">
                    <?= icon('upload', 'size-6') ?>
                    <b x-text="fileName || 'Choose a .csv, .tsv or .xlsx file'"></b>
                    <small>Max 2 MB · <?= IMPORT_MAX_ROWS ?> rows · first sheet is used (or the one named like the dataset)</small>
                    <input type="file" name="file" accept=".csv,.tsv,.txt,.xlsx" class="imp-file" @change="fileName = $event.target.files[0] ? $event.target.files[0].name : ''">
                </label>
            </div>
            <div x-show="tab === 'sheet'" x-cloak class="imp-panel">
                <label class="label" for="sheet_url">Google Sheets link</label>
                <input id="sheet_url" type="url" name="sheet_url" class="input" placeholder="https://docs.google.com/spreadsheets/d/…/edit#gid=0" maxlength="500">
                <p class="imp-hint">Set sharing to <b>“Anyone with the link” → Viewer</b>. Only the tab shown in the link (its <code>gid</code>) is imported. Private sheet? Use <b>File → Download → CSV / XLSX</b> and upload it instead.</p>
            </div>
            <div x-show="tab === 'paste'" x-cloak class="imp-panel">
                <label class="label" for="paste">Paste rows (including the header row)</label>
                <textarea id="paste" name="paste" rows="6" class="input imp-mono" placeholder="Title&#9;Platform&#9;Status&#10;My first post&#9;Instagram&#9;Draft"></textarea>
                <p class="imp-hint">Copy cells from Google Sheets or Excel and paste here (tab or comma separated).</p>
            </div>

            <div class="imp-actions">
                <button type="submit" class="btn-primary"><?= icon('upload', 'size-4') ?>Preview import</button>
            </div>
        </form>

        <section class="card imp-card">
            <h2 class="imp-h">Templates</h2>
            <p class="imp-p">Fill in a template, then upload it above. The XLSX templates include dropdown lists and a <b>Guide</b> sheet. For Google Sheets: download the CSV, then in Google Sheets choose <b>File → Import → Upload</b> (or paste the cells), fill it in, and use the link or CSV download here.</p>
            <ul class="imp-tpl">
                <?php foreach ($defs as $k => $d): ?>
                    <li>
                        <span class="imp-ico"><?= icon($d['icon'], 'size-5') ?></span>
                        <div class="imp-grow"><b><?= e($d['label']) ?></b><small><?= e($d['desc']) ?></small></div>
                        <a class="btn-ghost" href="<?= e(url('/import/template/' . $k . '.csv')) ?>" download><?= icon('download', 'size-4') ?>CSV</a>
                        <a class="btn-ghost" href="<?= e(url('/import/template/' . $k . '.xlsx')) ?>" download><?= icon('download', 'size-4') ?>XLSX</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <?php foreach ($defs as $k => $d): ?>
            <section class="card imp-card" x-show="ds === '<?= $k ?>'" <?= $k === $ds ? '' : 'x-cloak' ?>>
                <h2 class="imp-h"><?= e($d['label']) ?> columns</h2>
                <div class="imp-table-wrap">
                    <table class="imp-table">
                        <thead><tr><th>Column</th><th>Required</th><th>Accepted values</th></tr></thead>
                        <tbody>
                        <?php foreach ($d['cols'] as [$h, , $req, $hint]): ?>
                            <tr><td><b><?= e($h) ?></b></td><td><?= $req ? 'Yes' : 'No' ?></td><td><?= e($hint) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="imp-hint">Column order doesn’t matter, headers are case-insensitive, and unknown columns are ignored. Files created by the <a href="<?= e(url('/export')) ?>" class="imp-link">Export</a> page can be imported again.</p>
            </section>
        <?php endforeach; ?>
    </div>
    <?php
    page('Import', 'import', (string) ob_get_clean(), ['subtitle' => 'CSV, Excel or Google Sheets']);
}
