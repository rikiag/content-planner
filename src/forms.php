<?php
declare(strict_types=1);

defined('ROOT') || (http_response_code(403) && exit);

/**
 * Generic form field builder.
 * $o: options (select: value => label), req, ph, hint, rows, min, max, step, span (grid class), attrs
 */
function field(string $name, string $label, string $type = 'text', mixed $val = '', array $o = []): string
{
    $id = 'f-' . $name;
    $req = !empty($o['req']) ? ' required' : '';
    $ph = isset($o['ph']) ? ' placeholder="' . e($o['ph']) . '"' : '';
    $extra = $o['attrs'] ?? '';
    $span = $o['span'] ?? '';
    $html = '<div class="' . e($span) . '"><label for="' . $id . '" class="label">' . e($label) . (!empty($o['req']) ? ' <span class="text-rose-500">*</span>' : '') . '</label>';

    switch ($type) {
        case 'textarea':
            if (!empty($o['counter'])) {
                $max = (int) $o['counter'];
                $html .= '<div x-data="counter(' . mb_strlen((string) $val) . ', ' . $max . ')">'
                    . '<textarea id="' . $id . '" name="' . e($name) . '" rows="' . (int) ($o['rows'] ?? 4) . '" class="input resize-y"' . $ph . $req . ' ' . $extra . ' @input="n = $el.value.length">' . e($val) . '</textarea>'
                    . '<p class="mt-1 text-right text-[11px] font-semibold" :class="over ? \'text-rose-600\' : \'text-muted\'"><span x-text="n"></span> / ' . $max . ' characters</p></div>';
                break;
            }
            $html .= '<textarea id="' . $id . '" name="' . e($name) . '" rows="' . (int) ($o['rows'] ?? 4) . '" class="input resize-y"' . $ph . $req . ' ' . $extra . '>' . e($val) . '</textarea>';
            break;
        case 'select':
            $html .= '<select id="' . $id . '" name="' . e($name) . '" class="input"' . $req . ' ' . $extra . '>';
            foreach (($o['options'] ?? []) as $k => $lbl) {
                $html .= '<option value="' . e($k) . '"' . ((string) $k === (string) $val ? ' selected' : '') . '>' . e($lbl) . '</option>';
            }
            $html .= '</select>';
            break;
        case 'range':
            $html .= '<div class="flex items-center gap-3" x-data="{ v: ' . (int) $val . ' }"><input id="' . $id . '" type="range" name="' . e($name) . '" min="' . (int) ($o['min'] ?? 0) . '" max="' . (int) ($o['max'] ?? 100) . '" step="' . (int) ($o['step'] ?? 5) . '" x-model.number="v" class="h-2 flex-1 accent-[var(--c-brand)]">'
                . '<output for="' . $id . '" class="w-12 text-right text-sm font-bold" x-text="v + \'%\'">' . e($val) . '%</output></div>';
            break;
        case 'datetime':
            $html .= '<input id="' . $id . '" type="datetime-local" name="' . e($name) . '" value="' . e($val) . '" class="input"' . $req . ' ' . $extra . '>';
            break;
        case 'number':
            $html .= '<input id="' . $id . '" type="number" inputmode="numeric" name="' . e($name) . '" value="' . e($val) . '" min="' . (int) ($o['min'] ?? 0) . '"' . (isset($o['max']) ? ' max="' . (int) $o['max'] . '"' : '') . ' class="input"' . $req . ' ' . $extra . '>';
            break;
        default:
            $html .= '<input id="' . $id . '" type="' . e($type) . '" name="' . e($name) . '" value="' . e($val) . '" class="input"' . (isset($o['max']) ? ' maxlength="' . (int) $o['max'] . '"' : '') . $ph . $req . ' ' . $extra . '>';
    }
    if (!empty($o['hint'])) {
        $html .= '<p class="mt-1 text-[11px] text-muted">' . e($o['hint']) . '</p>';
    }
    return $html . '</div>';
}

/** Full-page form wrapper with sticky mobile action bar */
function form_shell(string $action, string $fieldsHtml, string $cancelHref, string $leftFooter = '', string $afterForm = ''): string
{
    return '<form method="post" action="' . e(url($action)) . '" class="mx-auto max-w-3xl" autocomplete="off">' . csrf_field()
        . '<input type="hidden" name="back" value="' . e(input('back')) . '">'
        . '<div class="card flex flex-col gap-4 p-5 sm:p-7">' . $fieldsHtml . '</div>'
        . '<div class="sticky bottom-[calc(4.6rem+env(safe-area-inset-bottom))] z-20 mt-4 flex flex-wrap items-center justify-between gap-2 rounded-3xl bg-surface/90 p-3 shadow-card backdrop-blur lg:static lg:bottom-auto lg:bg-transparent lg:p-0 lg:shadow-none">'
        . '<div>' . $leftFooter . '</div>'
        . '<div class="ml-auto flex items-center gap-2">'
        . '<a href="' . e(url($cancelHref)) . '" class="btn-ghost">Cancel</a>'
        . '<button type="submit" class="btn-primary">' . icon('check', 'size-4') . 'Save</button>'
        . '</div></div></form>' . $afterForm;
}

function section_label(string $title): string
{
    return '<h3 class="-mb-1 mt-2 text-xs font-extrabold uppercase tracking-wider text-brand">' . e($title) . '</h3>';
}

/** Merge current query parameters with overrides (null = remove) */
function qs(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, fn($v) => $v !== null && $v !== '');
    return $params ? '?' . http_build_query($params) : '';
}
