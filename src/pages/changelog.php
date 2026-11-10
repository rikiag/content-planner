<?php
declare(strict_types=1);

defined('ROOT') || (http_response_code(403) && exit);

/**
 * Public & Authenticated Changelog Page (`/changelog`).
 * Reads and parses the root `CHANGELOG.md` single source of truth on the server
 * (all output is HTML-escaped to stay safe under the strict CSP).
 */
function page_changelog(): void
{
    $user = current_user();
    $flashes = take_flash();
    $releases = parse_changelog(true);
    $ghUrl = GITHUB_URL;
    $releasesUrl = $ghUrl . '/releases';
    $atomUrl = $ghUrl . '/releases.atom';

    layout_head('Changelog · ' . APP_NAME);
?>

    <body data-base="<?= e(base_path()) ?>" data-version="<?= e(APP_VERSION) ?>" x-data="{ mobileMenu: false }" x-init="$store.changelog.markSeen()" @keydown.escape.window="mobileMenu = false">
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-[100] focus:rounded-xl focus:bg-surface focus:px-4 focus:py-2">Skip to content</a>

        <!-- ============ Top Header Banner Glow ============ -->
        <div class="fixed inset-x-0 top-0 -z-10 h-72 app-header opacity-30 [mask-image:linear-gradient(#000,transparent)]"></div>

        <!-- ============ Sticky Top Navbar ============ -->
        <header class="sticky top-0 z-40 border-b border-line bg-surface/95 backdrop-blur">
            <div class="mx-auto flex max-w-[1500px] items-center justify-between gap-3 px-4 py-3 sm:px-6">
                <a href="<?= e(url('/')) ?>" class="flex items-center gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-2xl bg-linear-to-br from-blue-500 to-indigo-600 text-white shadow-md shadow-blue-500/30"><?= icon('calendar-check', 'size-5.5') ?></span>
                    <div class="leading-tight">
                        <div class="flex items-center gap-2">
                            <span class="text-base font-extrabold tracking-tight sm:text-lg"><?= e(APP_NAME) ?></span>
                            <span class="hidden rounded-full bg-brandsoft px-2 py-0.5 text-[10px] font-extrabold text-brand sm:inline-block">v<?= e(APP_VERSION) ?> · MIT</span>
                        </div>
                        <span class="block text-[11px] text-muted">Release Notes &amp; Changelog</span>
                    </div>
                </a>

                <nav class="hidden items-center gap-1 lg:flex" aria-label="Changelog navigation">
                    <a href="<?= e(url('/welcome')) ?>" class="rounded-xl px-3 py-2 text-xs font-bold text-muted transition hover:bg-soft hover:text-ink">Overview</a>
                    <a href="<?= e(url('/welcome#features')) ?>" class="rounded-xl px-3 py-2 text-xs font-bold text-muted transition hover:bg-soft hover:text-ink">Features</a>
                    <a href="<?= e(url('/welcome#security')) ?>" class="rounded-xl px-3 py-2 text-xs font-bold text-muted transition hover:bg-soft hover:text-ink">Security</a>
                    <a href="<?= e(url('/welcome#quickstart')) ?>" class="rounded-xl px-3 py-2 text-xs font-bold text-muted transition hover:bg-soft hover:text-ink">Quick Start</a>
                    <a href="<?= e(url('/changelog')) ?>" aria-current="page" class="rounded-xl bg-soft px-3 py-2 text-xs font-extrabold text-brand">Changelog</a>
                </nav>

                <div class="flex items-center gap-2">
                    <button type="button" @click="$store.theme.toggle()" class="grid size-10 place-items-center rounded-xl border border-line bg-surface text-ink transition hover:bg-soft active:scale-95" aria-label="Toggle light/dark theme" title="Toggle theme">
                        <span x-show="!$store.theme.dark"><?= icon('moon', 'size-4.5') ?></span>
                        <span x-show="$store.theme.dark" x-cloak><?= icon('sun', 'size-4.5') ?></span>
                    </button>

                    <a href="<?= e($releasesUrl) ?>" target="_blank" rel="noopener noreferrer" class="hidden size-10 place-items-center rounded-xl border border-line bg-surface text-ink transition hover:bg-soft sm:grid" aria-label="GitHub Releases" title="View GitHub Releases">
                        <?= icon('github', 'size-4.5') ?>
                    </a>

                    <?php if ($user): ?>
                        <a href="<?= e(url('/')) ?>" class="btn-primary !px-3.5 !py-2 !text-xs sm:!px-4">
                            <span>Dashboard</span><?= icon('arrow-right', 'size-4') ?>
                        </a>
                    <?php else: ?>
                        <a href="<?= e(url('/login')) ?>" class="btn-ghost hidden !px-3.5 !py-2 !text-xs sm:inline-flex">Sign In</a>
                        <a href="<?= e(url('/register')) ?>" class="btn-primary !px-3.5 !py-2 !text-xs sm:!px-4">
                            <span>Get Started</span><?= icon('arrow-right', 'size-4') ?>
                        </a>
                    <?php endif; ?>

                    <button type="button" class="grid size-10 place-items-center rounded-xl border border-line bg-surface text-ink lg:hidden" @click="mobileMenu = !mobileMenu" :aria-expanded="mobileMenu" aria-label="Toggle navigation menu">
                        <span x-show="!mobileMenu"><?= icon('menu', 'size-5') ?></span>
                        <span x-show="mobileMenu" x-cloak><?= icon('x', 'size-5') ?></span>
                    </button>
                </div>
            </div>

            <!-- Mobile dropdown menu -->
            <div x-show="mobileMenu" x-cloak x-transition class="border-t border-line bg-surface px-4 pb-3 pt-2 lg:hidden">
                <div class="flex flex-col gap-1">
                    <a href="<?= e(url('/welcome')) ?>" class="rounded-xl px-3 py-2 text-sm font-bold text-ink hover:bg-soft">Product Overview</a>
                    <a href="<?= e(url('/welcome#features')) ?>" class="rounded-xl px-3 py-2 text-sm font-bold text-ink hover:bg-soft">Features</a>
                    <a href="<?= e(url('/welcome#quickstart')) ?>" class="rounded-xl px-3 py-2 text-sm font-bold text-ink hover:bg-soft">Quick Start Guide</a>
                    <a href="<?= e(url('/changelog')) ?>" class="rounded-xl bg-soft px-3 py-2 text-sm font-extrabold text-brand">Changelog</a>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2 border-t border-line pt-2">
                    <a href="<?= e($releasesUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn-ghost !py-2 !text-xs"><?= icon('github', 'size-4') ?>GitHub Releases</a>
                    <?php if ($user): ?>
                        <a href="<?= e(url('/')) ?>" class="btn-primary !py-2 !text-xs">Dashboard</a>
                    <?php else: ?>
                        <a href="<?= e(url('/login')) ?>" class="btn-soft !py-2 !text-xs">Sign In</a>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <main id="main" class="mx-auto max-w-4xl px-4 pb-14 pt-8 sm:px-6 sm:pt-12">
            <!-- ============ Header Hero ============ -->
            <div class="text-center">
                <div class="inline-flex flex-wrap items-center justify-center gap-2 rounded-full border border-line bg-surface/90 px-3.5 py-1.5 text-xs font-bold shadow-sm">
                    <span class="text-brand">Current Version: v<?= e(APP_VERSION) ?></span>
                    <span class="text-muted">•</span>
                    <a href="https://keepachangelog.com/en/1.1.0/" target="_blank" rel="noopener noreferrer" class="text-muted hover:text-ink hover:underline">Keep a Changelog</a>
                    <span class="text-muted">•</span>
                    <a href="https://semver.org/" target="_blank" rel="noopener noreferrer" class="text-muted hover:text-ink hover:underline">SemVer</a>
                </div>

                <h1 class="mt-4 text-3xl font-extrabold tracking-tight sm:text-4xl">What’s New in <?= e(APP_NAME) ?></h1>
                <p class="mx-auto mt-2.5 max-w-2xl text-sm leading-relaxed text-muted sm:text-base">
                    Rendered directly from <code class="rounded-md bg-soft px-1.5 py-0.5 font-mono text-xs font-bold text-brand">CHANGELOG.md</code>. Security updates and manual upgrade steps are highlighted clearly for self-hosted and shared hosting deployments.
                </p>

                <div class="mt-5 flex flex-wrap items-center justify-center gap-2.5">
                    <a href="<?= e($releasesUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn-dark !py-2.5 !text-xs">
                        <?= icon('github', 'size-4') ?><span>All Releases on GitHub</span>
                    </a>
                    <a href="<?= e($atomUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn-soft !py-2.5 !text-xs" title="Subscribe to GitHub Releases Atom feed">
                        <?= icon('bell', 'size-4') ?><span>Subscribe via Atom Feed</span>
                    </a>
                    <a href="<?= e(url('/welcome')) ?>" class="btn-ghost !py-2.5 !text-xs">
                        <span>← Back to Landing Page</span>
                    </a>
                </div>
            </div>

            <!-- ============ Timeline of Releases ============ -->
            <?php if (empty($releases)): ?>
                <div class="mt-10">
                    <?= empty_state('file', 'No releases documented yet', 'Check back soon or visit GitHub Releases for the latest updates.') ?>
                </div>
            <?php else: ?>
                <div class="mt-10 flex flex-col gap-6">
                    <?php foreach ($releases as $idx => $rel):
                        $isLatest = !$rel['is_unreleased'] && $idx === 0;
                        $tagUrl = $rel['is_unreleased']
                            ? ($ghUrl . '/compare/v' . rawurlencode(APP_VERSION) . '...HEAD')
                            : ($ghUrl . '/releases/tag/v' . rawurlencode($rel['version']));
                    ?>
                        <article id="v<?= e($rel['version']) ?>" class="scroll-mt-24">
                            <div class="card overflow-hidden border <?= $isLatest ? 'border-brand' : 'border-line' ?> p-5 sm:p-7">
                                <!-- Release Header -->
                                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line pb-4">
                                    <div class="flex flex-wrap items-center gap-2.5">
                                        <h2 class="text-xl font-extrabold tracking-tight sm:text-2xl">
                                            <?= $rel['is_unreleased'] ? 'Unreleased' : 'v' . e($rel['version']) ?>
                                        </h2>
                                        <?php if ($isLatest): ?>
                                            <span class="chip bg-brand text-white"><?= icon('sparkle', 'size-3.5') ?>Latest Release</span>
                                        <?php elseif ($rel['is_unreleased']): ?>
                                            <span class="chip bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">In Progress</span>
                                        <?php endif; ?>
                                        <?php if (isset($rel['categories']['Security'])): ?>
                                            <span class="chip bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300"><?= icon('shield', 'size-3.5') ?>Includes Security Update</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center gap-3 text-xs font-semibold text-muted">
                                        <?php if ($rel['date'] !== ''): ?>
                                            <time datetime="<?= e($rel['date']) ?>" class="inline-flex items-center gap-1.5">
                                                <?= icon('calendar', 'size-3.5') ?><?= e($rel['date_fmt']) ?>
                                            </time>
                                        <?php endif; ?>
                                        <a href="<?= e($tagUrl) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 font-bold text-brand hover:underline">
                                            <span>GitHub Tag</span><?= icon('external', 'size-3.5') ?>
                                        </a>
                                    </div>
                                </div>

                                <!-- Optional Release Feature Screenshot / GIF -->
                                <?php if (!empty($rel['image'])): ?>
                                    <div class="mt-5 overflow-hidden rounded-2xl border border-line bg-soft2 p-2">
                                        <img src="<?= e($rel['image']['src']) ?>"
                                            alt="<?= e($rel['image']['alt']) ?>"
                                            loading="<?= $idx === 0 ? 'eager' : 'lazy' ?>"
                                            width="1400" height="980"
                                            class="w-full rounded-xl border border-line">
                                    </div>
                                <?php endif; ?>

                                <!-- Categories (Added, Changed, Fixed, Removed, Security, Upgrade) -->
                                <div class="mt-5 flex flex-col gap-5">
                                    <?php foreach ($rel['categories'] as $catName => $items):
                                        $meta = changelog_category_meta($catName);
                                        $isSecurity = $catName === 'Security';
                                        $isUpgrade = $catName === 'Upgrade';
                                    ?>
                                        <div class="<?= ($isSecurity || $isUpgrade) ? 'rounded-2xl border p-4 ' . ($isSecurity ? 'border-rose-200 bg-rose-100/80 dark:border-rose-500/30 dark:bg-rose-500/10' : 'border-line bg-soft2') : '' ?>">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="chip <?= $meta['chip'] ?>">
                                                    <?= icon($meta['icon'], 'size-3.5') ?>
                                                    <span><?= e($catName) ?></span>
                                                </span>
                                                <?php if ($isSecurity): ?>
                                                    <span class="text-[11px] font-bold text-rose-600 dark:text-rose-400">Recommended update for shared hosting &amp; self-hosted servers</span>
                                                <?php elseif ($isUpgrade): ?>
                                                    <span class="text-[11px] font-bold text-brand">Upgrade &amp; migration notes</span>
                                                <?php endif; ?>
                                            </div>
                                            <ul class="mt-3 flex flex-col gap-2 text-xs leading-relaxed text-ink sm:text-sm">
                                                <?php foreach ($items as $item): ?>
                                                    <li class="flex items-start gap-2.5">
                                                        <span class="mt-1.5 size-1.5 shrink-0 rounded-full <?= $meta['dot'] ?>"></span>
                                                        <span><?= format_changelog_line($item) ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>

        <!-- ============ Footer ============ -->
        <footer class="border-t border-line bg-surface/90 py-8">
            <div class="mx-auto flex max-w-[1500px] flex-col items-center justify-between gap-4 px-4 text-center text-xs text-muted sm:flex-row sm:px-6 sm:text-left">
                <div class="flex items-center gap-2.5">
                    <span class="grid size-8 place-items-center rounded-xl bg-linear-to-br from-blue-500 to-indigo-600 text-white"><?= icon('calendar-check', 'size-4.5') ?></span>
                    <span>
                        <strong class="font-extrabold text-ink"><?= e(APP_NAME) ?></strong>
                        <span class="ml-1 rounded-md bg-soft px-1.5 py-0.5 font-mono text-[10px] font-bold text-brand">v<?= e(APP_VERSION) ?></span>
                        · Built by <a href="https://rikiag.com" target="_blank" rel="noopener noreferrer" class="font-bold text-brand hover:underline">Riki Agusnaidi (rikiag)</a>
                    </span>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-4 font-semibold">
                    <a href="<?= e(url('/welcome')) ?>" class="hover:text-ink">Landing Page</a>
                    <a href="<?= e($releasesUrl) ?>" target="_blank" rel="noopener noreferrer" class="hover:text-ink">GitHub Releases</a>
                    <a href="<?= e($atomUrl) ?>" target="_blank" rel="noopener noreferrer" class="hover:text-ink">Atom Feed</a>
                    <?php if ($user): ?>
                        <a href="<?= e(url('/')) ?>" class="text-brand hover:underline">Dashboard</a>
                    <?php else: ?>
                        <a href="<?= e(url('/login')) ?>" class="hover:text-ink">Sign In</a>
                    <?php endif; ?>
                </div>
            </div>
        </footer>

        <?php layout_overlays($flashes); ?>
    </body>

    </html>
<?php
}
