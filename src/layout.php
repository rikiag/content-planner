<?php

declare(strict_types=1);

defined('ROOT') || (http_response_code(403) && exit);

/** Primary navigation items (order matches sidebar) */
function nav_items(): array
{
    return [
        'home'      => ['label' => 'Dashboard', 'href' => '/',          'icon' => 'home',     'badge' => 0],
        'content'   => ['label' => 'Content',   'href' => '/content',   'icon' => 'calendar', 'badge' => overdue_count()],
        'campaign'  => ['label' => 'Campaigns', 'href' => '/campaigns', 'icon' => 'flag',     'badge' => 0],
        'idea'      => ['label' => 'Idea Bank', 'href' => '/ideas',     'icon' => 'bulb',     'badge' => new_ideas_count()],
        'resources' => ['label' => 'Resources', 'href' => '/resources', 'icon' => 'folder',   'badge' => 0],
        'analytics' => ['label' => 'Analytics', 'href' => '/analytics', 'icon' => 'chart',    'badge' => 0],
        'account'   => ['label' => 'Account',   'href' => '/account',   'icon' => 'users',    'badge' => 0],
    ];
}

/** Shared <head> for application and authentication pages */
function layout_head(string $title): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('Content-Security-Policy: ' . csp_header());
?>
    <!doctype html>
    <html lang="en" class="scroll-smooth">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title><?= e($title) ?></title>
        <meta name="description" content="Plan, schedule, and track your social media content performance in one place.">
        <meta name="theme-color" content="#2160d6">
        <meta name="color-scheme" content="light dark">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="Planner">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
        <link rel="icon" href="<?= e(url('icons/icon.svg')) ?>" type="image/svg+xml">
        <link rel="apple-touch-icon" href="<?= e(url('icons/apple-touch-icon.png')) ?>">
        <link rel="preload" href="<?= e(url('assets/fonts/plus-jakarta-sans-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
        <script nonce="<?= e(csp_nonce()) ?>">
            /* Apply theme before first paint to prevent flash: localStorage -> system preference */
            (function() {
                try {
                    var p = localStorage.getItem('cp-theme') || 'system';
                    var d = p === 'dark' || (p === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
                    var r = document.documentElement;
                    r.classList.toggle('dark', d);
                    r.dataset.theme = p;
                    var m = document.querySelector('meta[name=theme-color]');
                    if (m) m.content = d ? '#12306a' : '#2160d6';
                } catch (e) {}
            })();
        </script>
        <link rel="stylesheet" href="<?= e(asset('assets/app.css')) ?>">
        <!-- Order matters: app.js registers stores/components before Alpine initializes -->
        <script src="<?= e(asset('assets/app.js')) ?>" defer></script>
        <script src="<?= e(asset('assets/vendor/alpine.min.js')) ?>" defer></script>
    </head>
<?php
}

/** Global toast notifications and confirmation modal */
function layout_overlays(array $flashes): void
{
?>
    <div x-data x-init='$store.toast.seed(<?= e(json_encode($flashes, JSON_UNESCAPED_UNICODE)) ?>)'
        class="pointer-events-none fixed bottom-24 left-1/2 z-[80] flex w-[min(92vw,24rem)] -translate-x-1/2 flex-col items-center gap-2 lg:bottom-8" role="status" aria-live="polite">
        <template x-for="t in $store.toast.items" :key="t.id">
            <div class="toast pointer-events-auto flex items-center gap-2 rounded-2xl px-4 py-3 text-sm font-semibold text-white shadow-xl"
                :class="t.type === 'err' ? 'bg-rose-600' : 'bg-slate-900 dark:bg-slate-700'" @click="$store.toast.dismiss(t.id)">
                <span x-text="t.type === 'err' ? '✕' : '✓'" aria-hidden="true"></span><span x-text="t.msg"></span>
            </div>
        </template>
    </div>

    <div x-data x-cloak x-show="$store.confirm.open" x-transition.opacity
        x-effect="$store.confirm.open && $nextTick(() => $refs.ok && $refs.ok.focus())"
        @keydown.escape.window="$store.confirm.cancel()"
        class="fixed inset-0 z-[90] grid place-items-center bg-black/55 p-4 backdrop-blur-sm" role="alertdialog" aria-modal="true" aria-labelledby="cf-title">
        <div x-show="$store.confirm.open" x-transition.scale.95 @click.outside="$store.confirm.cancel()" class="card w-full max-w-sm p-6 text-center">
            <div class="mx-auto mb-3 grid size-12 place-items-center rounded-2xl bg-rose-100 text-rose-600 dark:bg-rose-500/20"><?= icon('trash', 'size-6') ?></div>
            <h2 id="cf-title" class="text-base font-extrabold">Confirm Action</h2>
            <p class="mt-1 text-sm text-muted" x-text="$store.confirm.msg"></p>
            <div class="mt-5 grid grid-cols-2 gap-2">
                <button type="button" class="btn-ghost" @click="$store.confirm.cancel()">Cancel</button>
                <button type="button" class="btn-danger" x-ref="ok" @click="$store.confirm.accept()">Yes, continue</button>
            </div>
        </div>
    </div>
<?php
}

/**
 * Render a full authenticated application page.
 * $opts: subtitle
 */
function page(string $title, string $active, string $body, array $opts = []): void
{
    $name = setting('name', 'User');
    $role = setting('role', 'Social Media Specialist');
    $studio = setting('studio', 'My Studio');
    $handle = setting('handle', '');
    $isHome = $active === 'home';
    $nav = nav_items();
    $rem = reminders();
    $remCount = count($rem['late']) + count($rem['soon']);
    $streak = streak_days();
    $views = (int) qv("SELECT COALESCE(SUM(views),0) FROM contents WHERE status='published' AND substr(" . pub_date_sql() . ",1,7)=?", [date('Y-m')]);
    $latestNote = q1('SELECT * FROM notes ORDER BY pinned DESC, updated_at DESC, id DESC LIMIT 1');
    $flashes = take_flash();
    $heading = $isHome ? 'Hello, ' . e($name) . ',' : e($title);
    $sub = $isHome ? e($role) : e($opts['subtitle'] ?? $studio);
    $back = request_path() . qs();

    layout_head($isHome ? APP_NAME : $title . ' · ' . APP_NAME);
?>

    <body class="pb-24 lg:pb-0" data-base="<?= e(base_path()) ?>"
        x-data="{ drawer: false }" @keydown.escape.window="drawer = false" :class="{ 'overflow-hidden': drawer }">
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-[100] focus:rounded-xl focus:bg-surface focus:px-4 focus:py-2">Skip to content</a>

        <div class="mx-auto flex max-w-[1500px] gap-5 lg:p-5">

            <!-- ============ Sidebar / drawer ============ -->
            <div x-show="drawer" x-cloak x-transition.opacity @click="drawer = false" class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm lg:hidden"></div>
            <aside id="drawer" :class="{ '!translate-x-0': drawer }"
                class="fixed inset-y-0 left-0 z-50 flex w-[290px] max-w-[86vw] -translate-x-full flex-col gap-4 overflow-y-auto bg-surface p-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-[max(1rem,env(safe-area-inset-top))] shadow-2xl transition-transform duration-300 lg:sticky lg:top-5 lg:z-0 lg:h-[calc(100dvh-2.5rem)] lg:w-[262px] lg:shrink-0 lg:translate-x-0 lg:rounded-[2rem] lg:shadow-card"
                aria-label="Main menu">
                <div class="flex items-center gap-3 px-1">
                    <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-linear-to-br from-blue-500 to-indigo-600 text-white shadow-md"><?= icon('calendar-check', 'size-6') ?></span>
                    <div class="min-w-0 leading-tight">
                        <div class="truncate text-[17px] font-extrabold tracking-tight"><?= e(APP_NAME) ?></div>
                        <div class="text-[11px] text-muted">by <a href="https://rikiag.com" target="_blank" rel="noopener noreferrer" class="hover:underline">rikiag</a> · <a href="<?= e(url('/welcome')) ?>" class="hover:underline">About</a></div>
                    </div>
                    <button class="ml-auto grid size-9 place-items-center rounded-xl text-muted hover:bg-soft lg:hidden" @click="drawer = false" aria-label="Close menu"><?= icon('x', 'size-5') ?></button>
                </div>

                <nav class="flex flex-col gap-1" aria-label="Primary navigation">
                    <?php foreach ($nav as $key => $it): ?>
                        <a href="<?= e(url($it['href'])) ?>" @click="drawer = false" class="nav-link" <?= $active === $key ? 'aria-current="page"' : '' ?>>
                            <?= icon($it['icon'], 'size-5') ?>
                            <span><?= e($it['label']) ?></span>
                            <?php if ($it['badge'] > 0): ?>
                                <span class="ml-auto grid min-w-5 place-items-center rounded-md px-1.5 py-0.5 text-[11px] font-bold <?= $active === $key ? 'bg-white/20 text-white' : 'bg-brandsoft text-brand' ?>"><?= (int) $it['badge'] ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <div class="rounded-3xl bg-linear-to-br from-brandsoft to-soft p-3.5">
                    <div class="flex items-start gap-2.5">
                        <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-amber-200 text-amber-800 dark:bg-amber-400/20 dark:text-amber-300"><?= icon('sheet', 'size-4.5') ?></span>
                        <div class="min-w-0">
                            <div class="text-xs font-extrabold">Export to Sheets</div>
                            <div class="text-[11px] leading-snug text-muted">Send your content schedule &amp; performance to Google Sheets.</div>
                        </div>
                    </div>
                    <a href="<?= e(url('/export')) ?>" class="btn-primary mt-3 w-full !rounded-xl !py-2 !text-xs">Export</a>
                </div>

                <div class="flex min-h-40 flex-1 flex-col rounded-3xl bg-note p-3.5 text-noteink" x-data="{ open: false }">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="grid size-6 place-items-center rounded-md bg-amber-400/90 text-amber-950"><?= icon('note', 'size-3.5') ?></span>
                        <a href="<?= e(url('/notes')) ?>" class="text-xs font-extrabold">Notes</a>
                        <button type="button" class="ml-auto grid size-6 place-items-center rounded-full bg-amber-400 text-amber-950 transition active:scale-90"
                            @click="open = !open; open && $nextTick(() => $refs.t.focus())" :aria-expanded="open" aria-label="Quick add note">
                            <span class="transition-transform" :class="{ 'rotate-45': open }"><?= icon('plus', 'size-3.5') ?></span>
                        </button>
                    </div>
                    <form x-show="open" x-cloak x-transition method="post" action="<?= e(url('/notes')) ?>">
                        <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
                        <textarea x-ref="t" name="body" required rows="3" maxlength="2000" placeholder="Write a note…" class="w-full resize-none rounded-xl border-0 bg-white/60 p-2 text-xs text-ink placeholder:text-noteink/60 focus:outline-2 focus:outline-amber-500 dark:bg-black/25 dark:text-white"></textarea>
                        <button class="mt-1.5 w-full rounded-lg bg-amber-400 py-1.5 text-[11px] font-bold text-amber-950">Save</button>
                    </form>
                    <?php if ($latestNote): ?>
                        <a href="<?= e(url('/notes')) ?>" class="line-clamp-6 whitespace-pre-line text-[11px] leading-relaxed"><?= e($latestNote['body']) ?></a>
                    <?php else: ?>
                        <p class="text-[11px] leading-relaxed opacity-80">No notes yet. Tap + to jot something down.</p>
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-2.5 px-1 pb-1">
                    <a href="<?= e(url('/account')) ?>" class="flex min-w-0 flex-1 items-center gap-2.5" aria-label="Account settings">
                        <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-brand text-xs font-extrabold text-white"><?= e(initials($studio)) ?></span>
                        <span class="min-w-0 leading-tight">
                            <span class="block truncate text-xs font-extrabold"><?= e($studio) ?></span>
                            <span class="block truncate text-[11px] text-muted"><?= e($handle ?: (current_user()['email'] ?? '')) ?></span>
                        </span>
                    </a>
                    <form method="post" action="<?= e(url('/logout')) ?>" @submit.prevent="cpLogout($el)">
                        <?= csrf_field() ?>
                        <button class="grid size-8 place-items-center rounded-lg text-muted hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10" aria-label="Log out" title="Log out"><?= icon('logout', 'size-4') ?></button>
                    </form>
                </div>
            </aside>

            <!-- ============ Main column ============ -->
            <div class="min-w-0 flex-1">
                <header class="app-header sticky top-0 z-30 text-white lg:static lg:rounded-[1.75rem] lg:shadow-card">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-3 px-4 pb-3 pt-[max(0.75rem,env(safe-area-inset-top))] lg:px-6 lg:py-4">
                        <button class="icon-btn lg:hidden" @click="drawer = true" aria-label="Open menu" aria-controls="drawer" :aria-expanded="drawer"><?= icon('menu', 'size-5') ?></button>
                        <div class="min-w-0 flex-1 leading-tight lg:flex-none lg:basis-56">
                            <h1 class="truncate text-lg font-extrabold tracking-tight lg:text-xl"><?= $heading ?></h1>
                            <p class="truncate text-[11px] font-medium text-white/80"><?= $sub ?></p>
                        </div>

                        <form action="<?= e(url('/search')) ?>" method="get" role="search" class="order-last w-full lg:order-none lg:max-w-[22rem] lg:flex-1">
                            <label class="relative block">
                                <span class="sr-only">Search content</span>
                                <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted"><?= icon('search', 'size-4') ?></span>
                                <input type="search" name="q" value="<?= e($active === 'search' ? input('q') : '') ?>" placeholder="Search content..." class="w-full rounded-2xl border-0 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-800 shadow-sm placeholder:text-slate-400 focus:outline-2 focus:outline-white/70 dark:bg-slate-900/70 dark:text-white">
                            </label>
                        </form>

                        <div class="flex items-center gap-1.5 sm:gap-2 lg:ml-auto">
                            <span class="hidden items-center gap-1.5 rounded-xl bg-white/15 px-3 py-2 text-xs font-bold sm:inline-flex" title="Consecutive posting days"><span class="text-orange-300"><?= icon('flame', 'size-4') ?></span><?= $streak ?></span>
                            <span class="hidden items-center gap-1.5 rounded-xl bg-white/15 px-3 py-2 text-xs font-bold sm:inline-flex" title="Total views this month"><span class="text-cyan-200"><?= icon('gem', 'size-4') ?></span><?= e(fmt_k($views)) ?></span>

                            <!-- Reminders dropdown -->
                            <div class="sm:relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                                <button class="icon-btn relative" @click="open = !open" :aria-expanded="open" aria-label="Reminders">
                                    <?= icon('bell', 'size-5') ?>
                                    <?php if ($remCount > 0): ?><span class="absolute right-2 top-2 size-2 rounded-full bg-rose-400 ring-2 ring-[#2f6ee0]"></span><?php endif; ?>
                                </button>
                                <div x-show="open" x-cloak x-transition.origin.top
                                    class="fixed inset-x-4 top-[calc(4.5rem+env(safe-area-inset-top))] z-50 mx-auto max-w-sm rounded-3xl bg-surface p-3 text-ink shadow-2xl ring-1 ring-line sm:absolute sm:inset-x-auto sm:right-0 sm:top-12 sm:mx-0 sm:w-[22rem] sm:max-w-none"
                                    role="dialog" aria-label="Reminders">
                                    <div class="flex items-center px-2 pb-2 pt-1"><span class="text-sm font-extrabold">Reminders</span><button class="ml-auto grid size-7 place-items-center rounded-lg text-muted hover:bg-soft sm:hidden" @click="open = false" aria-label="Close"><?= icon('x', 'size-4') ?></button></div>
                                    <?php if ($remCount === 0): ?>
                                        <p class="px-2 pb-3 text-xs text-muted">All caught up! No overdue content or posts due in the next 24 hours. 🎉</p>
                                    <?php else: ?>
                                        <ul class="flex max-h-[55vh] flex-col gap-1 overflow-y-auto">
                                            <?php foreach ($rem['late'] as $c): ?>
                                                <li><a href="<?= e(url('/content/' . $c['id'])) ?>" class="flex items-start gap-2.5 rounded-2xl p-2 hover:bg-soft">
                                                        <span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-lg bg-rose-100 text-rose-600 dark:bg-rose-500/20"><?= icon('clock', 'size-4') ?></span>
                                                        <span class="min-w-0 text-xs"><b class="line-clamp-1 text-sm"><?= e($c['title']) ?></b><span class="font-semibold text-rose-600 dark:text-rose-400">Overdue</span> · <?= e(fmt_dt($c['scheduled_at'])) ?></span></a></li>
                                            <?php endforeach; ?>
                                            <?php foreach ($rem['soon'] as $c): ?>
                                                <li><a href="<?= e(url('/content/' . $c['id'])) ?>" class="flex items-start gap-2.5 rounded-2xl p-2 hover:bg-soft">
                                                        <span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-lg bg-sky-100 text-sky-600 dark:bg-sky-500/20"><?= icon('calendar', 'size-4') ?></span>
                                                        <span class="min-w-0 text-xs"><b class="line-clamp-1 text-sm"><?= e($c['title']) ?></b>Scheduled <?= e(fmt_dt($c['scheduled_at'])) ?></span></a></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <button class="icon-btn" @click="$store.theme.toggle()" aria-label="Toggle light/dark theme" title="Toggle theme">
                                <span x-show="!$store.theme.dark"><?= icon('moon', 'size-5') ?></span>
                                <span x-show="$store.theme.dark" x-cloak><?= icon('sun', 'size-5') ?></span>
                            </button>
                            <button class="icon-btn hidden md:grid" @click="location.reload()" aria-label="Reload page"><?= icon('refresh', 'size-5') ?></button>
                            <a href="<?= e(url('/export')) ?>" class="icon-btn hidden md:grid" aria-label="Export to Sheets"><?= icon('sheet', 'size-5') ?></a>
                            <a href="<?= e(url('/content/new')) ?>" class="ml-1 hidden items-center gap-1.5 rounded-2xl bg-white px-4 py-2.5 text-sm font-bold text-slate-800 shadow-sm transition active:scale-95 sm:inline-flex dark:bg-slate-900/80 dark:text-white"><?= icon('plus', 'size-4') ?>New content</a>
                        </div>
                    </div>
                </header>

                <main id="main" class="px-4 pb-10 pt-4 lg:px-0 lg:pt-5">
                    <?= $body ?>
                </main>
            </div>
        </div>

        <!-- ============ Bottom navigation (mobile) ============ -->
        <nav class="bottom-nav fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden" aria-label="Bottom navigation">
            <ul class="mx-auto grid max-w-lg grid-cols-5 items-end px-2 pt-1.5">
                <?php
                $bottom = [['home', 'Home', '/', 'home'], ['content', 'Content', '/content', 'calendar'], null, ['idea', 'Ideas', '/ideas', 'bulb'], ['analytics', 'Analytics', '/analytics', 'chart']];
                foreach ($bottom as $b):
                    if ($b === null): ?>
                        <li class="grid place-items-center pb-1.5"><a href="<?= e(url('/content/new')) ?>" class="-mt-6 grid size-14 place-items-center rounded-full bg-linear-to-br from-blue-500 to-indigo-600 text-white shadow-lg shadow-blue-500/40 ring-4 ring-[var(--c-bg)] transition active:scale-90" aria-label="New content"><?= icon('plus', 'size-7') ?></a></li>
                    <?php else: [$k, $lbl, $href, $ic] = $b; ?>
                        <li><a href="<?= e(url($href)) ?>" class="flex flex-col items-center gap-0.5 rounded-xl py-1.5 text-[10.5px] font-semibold <?= $active === $k ? 'text-brand' : 'text-muted' ?>" <?= $active === $k ? 'aria-current="page"' : '' ?>>
                                <span class="relative"><?= icon($ic, 'size-5.5') ?>
                                    <?php if ($k === 'content' && overdue_count() > 0): ?><span class="absolute -right-1.5 -top-1 grid size-3.5 place-items-center rounded-full bg-rose-500 text-[8px] font-bold text-white"><?= overdue_count() ?></span><?php endif; ?>
                                </span><?= e($lbl) ?></a></li>
                <?php endif;
                endforeach; ?>
            </ul>
        </nav>

        <?php layout_overlays($flashes); ?>
    </body>

    </html>
<?php
}

/**
 * Unauthenticated layout (login / register).
 */
function auth_page(string $title, string $body): void
{
    $flashes = take_flash();
    layout_head($title . ' · ' . APP_NAME);
?>

    <body class="grid min-h-dvh place-items-center px-4 py-8" x-data data-base="<?= e(base_path()) ?>">
        <div class="fixed inset-x-0 top-0 -z-10 h-72 app-header [mask-image:linear-gradient(#000,transparent)]"></div>
        <button class="icon-btn fixed right-4 top-[max(1rem,env(safe-area-inset-top))] z-10 !bg-black/10 !text-ink dark:!bg-white/10" @click="$store.theme.toggle()" aria-label="Toggle light/dark theme">
            <span x-show="!$store.theme.dark"><?= icon('moon', 'size-5') ?></span><span x-show="$store.theme.dark" x-cloak><?= icon('sun', 'size-5') ?></span>
        </button>

        <main class="w-full max-w-md">
            <div class="mb-6 flex flex-col items-center text-center">
                <a href="<?= e(url('/')) ?>" class="group flex flex-col items-center">
                    <span class="grid size-16 place-items-center rounded-3xl bg-linear-to-br from-blue-500 to-indigo-600 text-white shadow-lg shadow-blue-500/30 transition group-hover:-translate-y-0.5"><?= icon('calendar-check', 'size-8') ?></span>
                    <h1 class="mt-4 text-2xl font-extrabold tracking-tight group-hover:text-brand"><?= e(APP_NAME) ?></h1>
                </a>
                <p class="mt-1 text-sm text-muted">Plan, schedule, and track your social media content.</p>
            </div>
            <div class="card p-6 sm:p-8"><?= $body ?></div>
            <p class="mt-6 text-center text-[11px] text-muted"><a href="<?= e(url('/')) ?>" class="font-semibold hover:text-ink">← Back to home</a> · Each workspace is isolated on this server.</p>
        </main>
        <?php layout_overlays($flashes); ?>
    </body>

    </html>
<?php
}
