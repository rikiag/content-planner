<?php
declare(strict_types=1);

defined('ROOT') || (http_response_code(403) && exit);

/**
 * Public Landing & About Page (`/` for guests, `/welcome` for everyone).
 * Built with PHP, Tailwind CSS 4, Alpine.js 3, and PWA support.
 */
function page_landing(): void
{
    $user = current_user();
    $flashes = take_flash();
    $ghUrl = 'https://github.com/rikiag/content-planner';
    $demoUrl = 'https://rikiag.com/content-planner/';
    $blogLicenseUrl = 'https://rikiag.com/id/blog/mit-gpl-apache-20-atau-proprietary-memilih-lisensi-yang-tepat-untuk-proyekmu';

    layout_head(APP_NAME . ' — Free & Open-Source Social Media Content Planner (PHP · SQLite · PWA)');

    $features = [
        [
            'icon'  => 'calendar',
            'color' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300',
            'badge' => '/content',
            'title' => 'List, Kanban Board & Calendar',
            'desc'  => 'Switch effortlessly between a detailed list, a 5-stage Kanban board (Idea → Draft → Review → Scheduled → Published), and a monthly calendar grid with inline status cycling.',
        ],
        [
            'icon'  => 'home',
            'color' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
            'badge' => '/',
            'title' => 'Actionable Studio Dashboard',
            'desc'  => 'Track monthly publishing progress, weekly posting goals, consecutive posting streaks, overdue & 24-hour reminders, in-progress drafts, and top-performing content.',
        ],
        [
            'icon'  => 'flag',
            'color' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
            'badge' => '/campaigns',
            'title' => 'Goal-Driven Campaigns',
            'desc'  => 'Group social posts into color-coded campaigns with date ranges and objectives. Automatically aggregate published post counts, total views, and engagement rates.',
        ],
        [
            'icon'  => 'bulb',
            'color' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
            'badge' => '/ideas',
            'title' => 'Idea Bank & 1-Click Conversion',
            'desc'  => 'Capture fleeting hooks and concepts in seconds. Tag ideas by content pillar and priority (Low, Medium, High), then convert any idea into a scheduled draft with one click.',
        ],
        [
            'icon'  => 'folder',
            'color' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
            'badge' => '/resources',
            'title' => 'Templates, Hashtags & Brand Vault',
            'desc'  => 'Keep reusable caption templates, hashtag sets, asset folder links, and brand guidelines in one searchable vault with instant one-click clipboard copy.',
        ],
        [
            'icon'  => 'chart',
            'color' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300',
            'badge' => '/analytics',
            'title' => 'Performance Analytics & KPIs',
            'desc'  => 'Compare 7, 30, or 90-day KPIs against the previous period. Visualize 8-week view trends, breakdowns by platform/format/pillar, and your best publishing days.',
        ],
        [
            'icon'  => 'note',
            'color' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
            'badge' => '/notes · /search',
            'title' => 'Pinned Sticky Notes & Global Search',
            'desc'  => 'Jot down color-coded sticky notes with pinning right in your sidebar, and search across content, campaigns, ideas, resources, and notes simultaneously.',
        ],
        [
            'icon'  => 'sheet',
            'color' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
            'badge' => '/export',
            'title' => 'Zero-Auth Google Sheets & Backups',
            'desc'  => 'Copy TSV tables directly into Google Sheets with one click, download formula-safe UTF-8 CSV reports, or export a complete JSON workspace backup anytime.',
        ],
        [
            'icon'  => 'smartphone',
            'color' => 'bg-pink-100 text-pink-700 dark:bg-pink-500/20 dark:text-pink-300',
            'badge' => 'PWA · Dark Mode',
            'title' => 'Installable PWA & System Dark Mode',
            'desc'  => 'Install on iOS, Android, macOS, or Windows with offline page caching via Service Worker. Flicker-free Light, Dark, and System themes synced across tabs.',
        ],
    ];

    $securityItems = [
        [
            'icon'  => 'shield',
            'title' => 'Patched Against PHP2Shell',
            'desc'  => 'Enforces PHP runtime versions patched against critical PHP2Shell memory-safety vulnerabilities (CVE-2026-109618 & CVE-2026-109619): PHP 8.2.35+, 8.3.36+, 8.4.27+, or 8.5.12+.',
        ],
        [
            'icon'  => 'database',
            'title' => 'Physical Per-User SQLite Isolation',
            'desc'  => 'data/auth.sqlite stores accounts and rate-limit counters, while every user gets a dedicated data/users/{id}.sqlite database in WAL mode. Queries can never cross user boundaries.',
        ],
        [
            'icon'  => 'lock',
            'title' => 'Hardened Authentication & CSRF',
            'desc'  => 'Passwords hashed via password_hash(PASSWORD_DEFAULT), timing-safe login verification, session ID regeneration, HttpOnly + SameSite=Lax cookies, CSRF tokens on every POST, and brute-force rate limiting (5 attempts / 15 min).',
        ],
        [
            'icon'  => 'terminal',
            'title' => 'Strict CSP with Cryptographic Nonces',
            'desc'  => 'Every response emits a per-request Content-Security-Policy nonce, X-Content-Type-Options: nosniff, X-Frame-Options: SAMEORIGIN, and private error logging with automatic rotation.',
        ],
    ];

    $routesList = [
        ['/', 'Dashboard (or Landing Page for guests): monthly progress, quick navigation, continue working, pipeline breakdown, weekly goal, top posts'],
        ['/welcome', 'Product overview, interactive feature showcase, architecture, quick start guide, and open-source license'],
        ['/content', 'Content Plan: List · Board (Kanban) · Calendar views; filter by status/platform/campaign; quick inline status cycling'],
        ['/content/new, /content/{id}', 'Create or edit a content item (schedule, caption, hashtags, completion progress, performance metrics)'],
        ['/campaigns, /campaigns/{id}', 'Campaigns list and campaign detail with performance summary & assigned posts'],
        ['/ideas', 'Idea Bank: quick capture, priority/pillar tagging, one-click Turn into post conversion into content'],
        ['/resources', 'Caption templates, hashtag sets, links, and brand assets with one-click copy'],
        ['/analytics', 'KPIs vs. previous period, 8-week view trend, breakdown by platform/format/pillar, best publishing days, top posts'],
        ['/account', 'Profile & goals, theme switcher, PWA install prompt, JSON backup, sample data reset, account deletion'],
        ['/notes', 'Color-coded sticky notes with pinning; pinned/recent notes appear in the sidebar'],
        ['/export', 'One-click TSV copy for Google Sheets, CSV downloads (content.csv, campaigns.csv, ideas.csv), full JSON backup'],
        ['/search?q=', 'Global search across content, campaigns, ideas, resources, and notes'],
    ];
?>

    <body data-base="<?= e(base_path()) ?>" x-data="{ mobileMenu: false, activeTab: 'views', installTab: 'cli' }" @keydown.escape.window="mobileMenu = false">
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
                        <span class="block text-[11px] text-muted">Open-Source Social Media Workspace</span>
                    </div>
                </a>

                <nav class="hidden items-center gap-1 lg:flex" aria-label="Landing navigation">
                    <a href="#interactive" class="rounded-xl px-3 py-2 text-xs font-bold text-muted transition hover:bg-soft hover:text-ink">Workflow</a>
                    <a href="#features" class="rounded-xl px-3 py-2 text-xs font-bold text-muted transition hover:bg-soft hover:text-ink">Features</a>
                    <a href="#security" class="rounded-xl px-3 py-2 text-xs font-bold text-muted transition hover:bg-soft hover:text-ink">Security</a>
                    <a href="#quickstart" class="rounded-xl px-3 py-2 text-xs font-bold text-muted transition hover:bg-soft hover:text-ink">Quick Start</a>
                    <a href="#routes" class="rounded-xl px-3 py-2 text-xs font-bold text-muted transition hover:bg-soft hover:text-ink">Routes</a>
                    <a href="#license" class="rounded-xl px-3 py-2 text-xs font-bold text-muted transition hover:bg-soft hover:text-ink">License</a>
                </nav>

                <div class="flex items-center gap-2">
                    <button type="button" x-show="$store.pwa.can" x-cloak @click="$store.pwa.install()" class="btn-soft hidden !px-3.5 !py-2 !text-xs sm:inline-flex" title="Install Progressive Web App">
                        <?= icon('smartphone', 'size-4') ?><span>Install App</span>
                    </button>

                    <button type="button" @click="$store.theme.toggle()" class="grid size-10 place-items-center rounded-xl border border-line bg-surface text-ink transition hover:bg-soft active:scale-95" aria-label="Toggle light/dark theme" title="Toggle theme">
                        <span x-show="!$store.theme.dark"><?= icon('moon', 'size-4.5') ?></span>
                        <span x-show="$store.theme.dark" x-cloak><?= icon('sun', 'size-4.5') ?></span>
                    </button>

                    <a href="<?= e($ghUrl) ?>" target="_blank" rel="noopener noreferrer" class="hidden size-10 place-items-center rounded-xl border border-line bg-surface text-ink transition hover:bg-soft sm:grid" aria-label="GitHub repository" title="View source on GitHub">
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
                    <a href="#interactive" @click="mobileMenu = false" class="rounded-xl px-3 py-2 text-sm font-bold text-ink hover:bg-soft">Interactive Workflow</a>
                    <a href="#features" @click="mobileMenu = false" class="rounded-xl px-3 py-2 text-sm font-bold text-ink hover:bg-soft">Features</a>
                    <a href="#security" @click="mobileMenu = false" class="rounded-xl px-3 py-2 text-sm font-bold text-ink hover:bg-soft">Security &amp; PHP2Shell Patch</a>
                    <a href="#quickstart" @click="mobileMenu = false" class="rounded-xl px-3 py-2 text-sm font-bold text-ink hover:bg-soft">Quick Start Guide</a>
                    <a href="#routes" @click="mobileMenu = false" class="rounded-xl px-3 py-2 text-sm font-bold text-ink hover:bg-soft">Routes &amp; Architecture</a>
                    <a href="#license" @click="mobileMenu = false" class="rounded-xl px-3 py-2 text-sm font-bold text-ink hover:bg-soft">Free &amp; Open Source (MIT)</a>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2 border-t border-line pt-2">
                    <a href="<?= e($ghUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn-ghost !py-2 !text-xs"><?= icon('github', 'size-4') ?>GitHub Repo</a>
                    <?php if ($user): ?>
                        <a href="<?= e(url('/')) ?>" class="btn-primary !py-2 !text-xs">Dashboard</a>
                    <?php else: ?>
                        <a href="<?= e(url('/login')) ?>" class="btn-soft !py-2 !text-xs">Sign In</a>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <main id="main" class="mx-auto max-w-[1500px] px-4 pb-10 sm:px-6">
            <!-- ============ Hero Section ============ -->
            <section class="py-10 sm:py-12">
                <div class="mx-auto max-w-4xl text-center">
                    <div class="inline-flex flex-wrap items-center justify-center gap-2 rounded-full border border-line bg-surface/90 px-3.5 py-1.5 text-xs font-bold shadow-sm">
                        <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400"><?= icon('shield', 'size-3.5') ?>PHP2Shell Patched</span>
                        <span class="text-muted">•</span>
                        <span class="text-brand">100% Free &amp; Open Source</span>
                        <span class="text-muted">•</span>
                        <span class="text-muted">PWA Offline-Ready</span>
                    </div>

                    <h1 class="mt-5 text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl lg:text-5xl">
                        Plan, Schedule &amp; Track Your
                        <span class="text-brand">Social Media Content</span>
                        in One Place.
                    </h1>

                    <p class="mx-auto mt-4 max-w-2xl text-sm leading-relaxed text-muted sm:text-base lg:text-lg">
                        A fast, lightweight, self-hosted social media content planner built with
                        <strong class="font-bold text-ink">PHP · SQLite · Tailwind CSS 4 · Alpine.js 3</strong>.
                        Mobile-first UI, system-aware dark mode, physical per-user database isolation, and installable Progressive Web App (PWA) support — with zero bloat.
                    </p>

                    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                        <?php if ($user): ?>
                            <a href="<?= e(url('/')) ?>" class="btn-primary !py-3 px-6 shadow-lg shadow-blue-500/30">
                                <?= icon('calendar-check', 'size-4.5') ?><span>Go to Your Dashboard</span><?= icon('arrow-right', 'size-4') ?>
                            </a>
                        <?php else: ?>
                            <a href="<?= e(url('/register')) ?>" class="btn-primary !py-3 px-6 shadow-lg shadow-blue-500/30">
                                <?= icon('sparkle', 'size-4.5') ?><span>Create Free Workspace</span><?= icon('arrow-right', 'size-4') ?>
                            </a>
                            <a href="<?= e(url('/login')) ?>" class="btn-ghost !py-3 px-5">
                                <?= icon('user', 'size-4.5') ?><span>Sign In</span>
                            </a>
                        <?php endif; ?>
                        <a href="<?= e($ghUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn-dark !py-3 px-5">
                            <?= icon('github', 'size-4.5') ?><span>View on GitHub</span>
                        </a>
                        <a href="<?= e($demoUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn-soft !py-3 px-5">
                            <?= icon('external', 'size-4') ?><span>Live Demo</span>
                        </a>
                    </div>

                    <!-- Tech & Platform Pills -->
                    <div class="mt-6 flex flex-wrap items-center justify-center gap-2 text-xs font-semibold text-muted">
                        <span class="rounded-full bg-surface px-3 py-1.5 shadow-sm ring-1 ring-line">PHP 8.2.35+ / 8.3.36+ / 8.4.27+ / 8.5.12+</span>
                        <span class="rounded-full bg-surface px-3 py-1.5 shadow-sm ring-1 ring-line">SQLite WAL Per-User DB</span>
                        <span class="rounded-full bg-surface px-3 py-1.5 shadow-sm ring-1 ring-line">Tailwind CSS 4</span>
                        <span class="rounded-full bg-surface px-3 py-1.5 shadow-sm ring-1 ring-line">Alpine.js 3</span>
                        <span class="rounded-full bg-surface px-3 py-1.5 shadow-sm ring-1 ring-line">Progressive Web App (PWA)</span>
                        <span class="rounded-full bg-surface px-3 py-1.5 shadow-sm ring-1 ring-line">No Node.js Required in Prod</span>
                    </div>
                </div>

                <!-- ============ Dashboard Screenshot Showcase ============ -->
                <div class="relative mx-auto mt-10 max-w-5xl">
                    <div class="overflow-hidden rounded-[1.75rem] border border-line bg-surface p-2 shadow-2xl sm:p-4 lg:rounded-[2rem]">
                        <div class="mb-2 flex items-center justify-between rounded-2xl bg-soft px-4 py-2 text-xs">
                            <div class="flex items-center gap-1.5">
                                <span class="size-2.5 rounded-full bg-rose-500"></span>
                                <span class="size-2.5 rounded-full bg-amber-500"></span>
                                <span class="size-2.5 rounded-full bg-emerald-500"></span>
                            </div>
                            <span class="truncate font-mono text-[11px] font-semibold text-muted">https://rikiag.com/content-planner/</span>
                            <span class="hidden text-[11px] font-bold text-brand sm:inline">PWA · Offline Ready</span>
                        </div>
                        <img src="<?= e(asset('assets/images/screenshot.webp')) ?>"
                            alt="Content Planner Dashboard Screenshot showing monthly progress ring, quick access tiles, weekly posting goal, and top content rankings"
                            width="1400" height="980"
                            class="w-full rounded-2xl border border-line">
                    </div>

                    <!-- Key Highlights Strip -->
                    <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <div class="card flex items-center gap-3 p-4">
                            <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300"><?= icon('board', 'size-5.5') ?></span>
                            <div>
                                <div class="text-lg font-extrabold leading-none">3 Views</div>
                                <div class="mt-1 text-xs text-muted">List · Kanban · Calendar</div>
                            </div>
                        </div>
                        <div class="card flex items-center gap-3 p-4">
                            <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300"><?= icon('database', 'size-5.5') ?></span>
                            <div>
                                <div class="text-lg font-extrabold leading-none">Zero Setup DB</div>
                                <div class="mt-1 text-xs text-muted">Isolated SQLite per user</div>
                            </div>
                        </div>
                        <div class="card flex items-center gap-3 p-4">
                            <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300"><?= icon('flame', 'size-5.5') ?></span>
                            <div>
                                <div class="text-lg font-extrabold leading-none">6 Platforms</div>
                                <div class="mt-1 text-xs text-muted">IG · TT · FB · YT · LI · X</div>
                            </div>
                        </div>
                        <div class="card flex items-center gap-3 p-4">
                            <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300"><?= icon('smartphone', 'size-5.5') ?></span>
                            <div>
                                <div class="text-lg font-extrabold leading-none">Installable PWA</div>
                                <div class="mt-1 text-xs text-muted">Works on mobile &amp; desktop</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============ Interactive Workflow Preview (Alpine.js) ============ -->
            <section id="interactive" class="mx-auto max-w-5xl scroll-mt-20 py-10">
                <div class="mx-auto max-w-3xl text-center">
                    <span class="chip bg-brandsoft text-brand">Interactive Workflow Preview</span>
                    <h2 class="mt-3 text-2xl font-extrabold tracking-tight sm:text-3xl">Designed for Creators, Studios &amp; Social Teams</h2>
                    <p class="mt-2 text-sm text-muted sm:text-base">Explore how Content Planner takes your posts from raw spark to published analytics without spreadsheet chaos.</p>
                </div>

                <!-- Tab Triggers -->
                <div class="mt-6 flex justify-center">
                    <div class="grid w-full max-w-3xl grid-cols-2 gap-1.5 rounded-3xl bg-surface p-1.5 shadow-card sm:grid-cols-4" role="tablist" aria-label="Workflow stages">
                        <button type="button" role="tab" :aria-selected="activeTab === 'views'" @click="activeTab = 'views'"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl px-3.5 py-2.5 text-xs font-extrabold transition"
                            :class="activeTab === 'views' ? 'bg-hero text-white shadow-md' : 'text-muted hover:bg-soft hover:text-ink'">
                            <?= icon('board', 'size-4') ?><span>1. Pipeline &amp; Views</span>
                        </button>
                        <button type="button" role="tab" :aria-selected="activeTab === 'ideas'" @click="activeTab = 'ideas'"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl px-3.5 py-2.5 text-xs font-extrabold transition"
                            :class="activeTab === 'ideas' ? 'bg-hero text-white shadow-md' : 'text-muted hover:bg-soft hover:text-ink'">
                            <?= icon('bulb', 'size-4') ?><span>2. Ideas &amp; Campaigns</span>
                        </button>
                        <button type="button" role="tab" :aria-selected="activeTab === 'analytics'" @click="activeTab = 'analytics'"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl px-3.5 py-2.5 text-xs font-extrabold transition"
                            :class="activeTab === 'analytics' ? 'bg-hero text-white shadow-md' : 'text-muted hover:bg-soft hover:text-ink'">
                            <?= icon('chart', 'size-4') ?><span>3. KPIs &amp; Analytics</span>
                        </button>
                        <button type="button" role="tab" :aria-selected="activeTab === 'export'" @click="activeTab = 'export'"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl px-3.5 py-2.5 text-xs font-extrabold transition"
                            :class="activeTab === 'export' ? 'bg-hero text-white shadow-md' : 'text-muted hover:bg-soft hover:text-ink'">
                            <?= icon('sheet', 'size-4') ?><span>4. Vault &amp; Sheets</span>
                        </button>
                    </div>
                </div>

                <!-- Tab Panels -->
                <div class="mt-5">
                    <!-- Panel 1: Pipeline & Views -->
                    <div x-show="activeTab === 'views'" x-transition.opacity class="card p-5 sm:p-8">
                        <div class="grid grid-cols-1 items-center gap-6 lg:grid-cols-2">
                            <div>
                                <span class="chip bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300">List · Kanban Board · Calendar</span>
                                <h3 class="mt-3 text-xl font-extrabold sm:text-2xl">5-Stage Content Pipeline</h3>
                                <p class="mt-2 text-sm leading-relaxed text-muted">
                                    Every post flows cleanly through <b>Idea → Draft → Review → Scheduled → Published</b>. Filter by platform, campaign, or status with instant client-side search (<code>liveFilter</code>) and update status inline with a single tap.
                                </p>
                                <ul class="mt-4 flex flex-col gap-2.5 text-xs font-semibold sm:text-sm">
                                    <li class="flex items-center gap-2.5"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20"><?= icon('check', 'size-3.5') ?></span>Supported Platforms: Instagram, TikTok, Facebook, YouTube, LinkedIn, X</li>
                                    <li class="flex items-center gap-2.5"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20"><?= icon('check', 'size-3.5') ?></span>Formats: Feed, Carousel, Reels, Story, Video, Thread</li>
                                    <li class="flex items-center gap-2.5"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20"><?= icon('check', 'size-3.5') ?></span>Completion progress slider (0–100%), caption counter (2,200 chars), &amp; overdue alerts</li>
                                </ul>
                            </div>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <?php
                                $sampleCols = [
                                    ['Draft', 'bg-rose-500', 'bg-rose-100/80 dark:bg-rose-500/10', [
                                        ['IG', 'bg-pink-100 text-pink-700 dark:bg-pink-500/20 dark:text-pink-300', 'Morning Mobility Routine', 'Reels · Tomorrow 09:00', 70],
                                        ['TT', 'bg-slate-200 text-slate-800 dark:bg-slate-500/25 dark:text-slate-200', '3 Desk Posture Fixes', 'Video · Thu 17:00', 45],
                                    ]],
                                    ['Scheduled', 'bg-sky-500', 'bg-sky-100/80 dark:bg-sky-500/10', [
                                        ['LI', 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300', 'Behind Our Studio Launch', 'Carousel · Fri 10:00', 100],
                                        ['YT', 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300', '15-Min Full Body Stretch', 'Video · Sat 08:00', 100],
                                    ]],
                                    ['Published', 'bg-emerald-500', 'bg-emerald-100/80 dark:bg-emerald-500/10', [
                                        ['IG', 'bg-pink-100 text-pink-700 dark:bg-pink-500/20 dark:text-pink-300', '5-Minute Daily Checklist', 'Carousel · 2.7K views · ER 12.9%', 100],
                                        ['TT', 'bg-slate-200 text-slate-800 dark:bg-slate-500/25 dark:text-slate-200', 'Bedtime Stretch Flow', 'Reels · 1.8K views · ER 13.8%', 100],
                                    ]],
                                ];
                                foreach ($sampleCols as [$colTitle, $dotCls, $tintCls, $cards]): ?>
                                    <div class="rounded-3xl p-3.5 <?= $tintCls ?>">
                                        <div class="mb-3 flex items-center gap-2">
                                            <span class="size-2.5 rounded-full <?= $dotCls ?>"></span>
                                            <span class="text-xs font-extrabold"><?= $colTitle ?></span>
                                            <span class="ml-auto rounded-full bg-surface px-2 py-0.5 text-[10px] font-bold"><?= count($cards) ?></span>
                                        </div>
                                        <div class="flex flex-col gap-2.5">
                                            <?php foreach ($cards as [$abbr, $pCls, $cTitle, $cMeta, $cProg]): ?>
                                                <div class="rounded-2xl bg-surface p-3 shadow-sm">
                                                    <div class="flex items-start gap-2">
                                                        <span class="chip <?= $pCls ?>"><?= $abbr ?></span>
                                                        <span class="text-xs font-bold leading-snug"><?= $cTitle ?></span>
                                                    </div>
                                                    <div class="mt-1.5 text-[11px] text-muted"><?= $cMeta ?></div>
                                                    <div class="bar-track mt-2"><div class="h-full rounded-full <?= $colTitle === 'Published' ? 'bg-emerald-500' : 'bg-accent' ?>" style="width:<?= $cProg ?>%"></div></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Panel 2: Idea Bank & Campaigns -->
                    <div x-show="activeTab === 'ideas'" x-cloak x-transition.opacity class="card p-5 sm:p-8">
                        <div class="grid grid-cols-1 items-center gap-6 lg:grid-cols-2">
                            <div>
                                <span class="chip bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">Idea Bank &amp; Campaigns</span>
                                <h3 class="mt-3 text-xl font-extrabold sm:text-2xl">Turn Sparks into Campaigns</h3>
                                <p class="mt-2 text-sm leading-relaxed text-muted">
                                    Never lose a content hook again. Save ideas tagged with content pillars (<b>Education, Inspiration, Promotion, Community, Behind the Scenes, Entertainment</b>) and convert them into scheduled content items in a single click.
                                </p>
                                <ul class="mt-4 flex flex-col gap-2.5 text-xs font-semibold sm:text-sm">
                                    <li class="flex items-center gap-2.5"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20"><?= icon('check', 'size-3.5') ?></span>One-click <b>Turn into post</b> pre-fills title, pillar, and outline</li>
                                    <li class="flex items-center gap-2.5"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20"><?= icon('check', 'size-3.5') ?></span>Color-coded Campaigns track progress %, views, and ER%</li>
                                    <li class="flex items-center gap-2.5"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20"><?= icon('check', 'size-3.5') ?></span>Priority sorting (High, Medium, Low) &amp; bookmarking</li>
                                </ul>
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="rounded-3xl bg-linear-to-br from-blue-500 to-indigo-600 p-5 text-white shadow-md">
                                    <span class="chip bg-white/25 text-white">Active Campaign</span>
                                    <h4 class="mt-2 text-lg font-extrabold">21-Day Active Challenge</h4>
                                    <p class="mt-1 text-xs text-white/85">Drive daily community participation &amp; story tags</p>
                                    <div class="mt-4">
                                        <div class="mb-1 flex justify-between text-[11px] font-bold"><span>6 / 8 posts published</span><span>75%</span></div>
                                        <div class="bar-track !bg-white/15"><div class="h-full rounded-full bg-emerald-400" style="width:75%"></div></div>
                                    </div>
                                    <div class="mt-4 grid grid-cols-2 gap-2 text-center">
                                        <div class="rounded-2xl bg-white/15 py-2"><div class="text-[10px] text-white/80">Total Views</div><div class="text-base font-extrabold">9.4K</div></div>
                                        <div class="rounded-2xl bg-white/15 py-2"><div class="text-[10px] text-white/80">Avg. ER</div><div class="text-base font-extrabold">13.2%</div></div>
                                    </div>
                                </div>
                                <div class="flex flex-col justify-between rounded-3xl border border-line bg-soft2 p-5">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="chip bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300">New Idea</span>
                                            <span class="chip bg-soft text-muted">Education</span>
                                            <span class="ml-auto text-[11px] font-bold text-rose-600 dark:text-rose-400">● High</span>
                                        </div>
                                        <h4 class="mt-3 text-base font-extrabold">Myth vs. Fact: Static Stretching Before Workouts</h4>
                                        <p class="mt-1.5 text-xs leading-relaxed text-muted">Hook: Stop holding 60-second stretches cold! Show 3 dynamic warm-up swaps instead.</p>
                                    </div>
                                    <div class="mt-4 flex items-center justify-between border-t border-line pt-3">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-hero px-3.5 py-2 text-xs font-bold text-white"><?= icon('calendar', 'size-3.5') ?>Turn into post</span>
                                        <span class="text-xs font-semibold text-muted">1-click convert</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Panel 3: Analytics -->
                    <div x-show="activeTab === 'analytics'" x-cloak x-transition.opacity class="card p-5 sm:p-8">
                        <div class="grid grid-cols-1 items-center gap-6 lg:grid-cols-2">
                            <div>
                                <span class="chip bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300">Performance Analytics</span>
                                <h3 class="mt-3 text-xl font-extrabold sm:text-2xl">Know What Resonates</h3>
                                <p class="mt-2 text-sm leading-relaxed text-muted">
                                    Log views, likes, comments, shares, and saves on published posts. Content Planner computes engagement rates (<code>(likes + comments + shares + saves) / views</code>), period-over-period deltas, and your best publishing days automatically.
                                </p>
                                <ul class="mt-4 flex flex-col gap-2.5 text-xs font-semibold sm:text-sm">
                                    <li class="flex items-center gap-2.5"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20"><?= icon('check', 'size-3.5') ?></span>7-day, 30-day, 90-day, and All-time date range comparisons</li>
                                    <li class="flex items-center gap-2.5"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20"><?= icon('check', 'size-3.5') ?></span>8-week view trend chart &amp; best day of the week analysis</li>
                                    <li class="flex items-center gap-2.5"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20"><?= icon('check', 'size-3.5') ?></span>Breakdowns by Platform, Format, and Content Pillar</li>
                                </ul>
                            </div>
                            <div>
                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    <div class="rounded-2xl bg-soft2 p-3.5">
                                        <div class="text-[11px] font-semibold text-muted">Views (30d)</div>
                                        <div class="mt-1 text-xl font-extrabold">13.5K</div>
                                        <div class="mt-0.5 text-[11px] font-bold text-emerald-600 dark:text-emerald-400">▲ +28%</div>
                                    </div>
                                    <div class="rounded-2xl bg-soft2 p-3.5">
                                        <div class="text-[11px] font-semibold text-muted">Interactions</div>
                                        <div class="mt-1 text-xl font-extrabold">1.7K</div>
                                        <div class="mt-0.5 text-[11px] font-bold text-emerald-600 dark:text-emerald-400">▲ +34%</div>
                                    </div>
                                    <div class="rounded-2xl bg-soft2 p-3.5">
                                        <div class="text-[11px] font-semibold text-muted">Avg. ER</div>
                                        <div class="mt-1 text-xl font-extrabold">12.8%</div>
                                        <div class="mt-0.5 text-[11px] font-bold text-emerald-600 dark:text-emerald-400">▲ +1.4 pp</div>
                                    </div>
                                    <div class="rounded-2xl bg-soft2 p-3.5">
                                        <div class="text-[11px] font-semibold text-muted">Streak</div>
                                        <div class="mt-1 text-xl font-extrabold">8 Days</div>
                                        <div class="mt-0.5 text-[11px] font-bold text-amber-600 dark:text-amber-400">On target</div>
                                    </div>
                                </div>
                                <div class="mt-4 rounded-3xl bg-soft2 p-4">
                                    <div class="mb-3 flex items-center justify-between text-xs font-extrabold">
                                        <span>Weekly Views Trend (Last 8 Weeks)</span>
                                        <span class="text-muted">Peak: 3.4K views</span>
                                    </div>
                                    <div class="flex h-44 items-end gap-2">
                                        <?php foreach ([35, 48, 42, 62, 58, 75, 84, 100] as $idx => $h): ?>
                                            <div class="flex h-full flex-1 flex-col items-center justify-end gap-1">
                                                <div class="w-full rounded-t-xl <?= $idx === 7 ? 'bg-accent' : 'bg-brand/70' ?>" style="height:<?= $h ?>%"></div>
                                                <span class="text-[9px] font-bold text-muted">W<?= $idx + 1 ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Panel 4: Vault, Sticky Notes & Google Sheets -->
                    <div x-show="activeTab === 'export'" x-cloak x-transition.opacity class="card p-5 sm:p-8">
                        <div class="grid grid-cols-1 items-center gap-6 lg:grid-cols-2">
                            <div>
                                <span class="chip bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">Resources · Notes · Data Portability</span>
                                <h3 class="mt-3 text-xl font-extrabold sm:text-2xl">Your Data Belongs to You</h3>
                                <p class="mt-2 text-sm leading-relaxed text-muted">
                                    Copy reusable caption templates and hashtag sets in one click, pin sticky notes to your sidebar, paste your schedule straight into Google Sheets without OAuth setup, or download full CSV/JSON backups.
                                </p>
                                <ul class="mt-4 flex flex-col gap-2.5 text-xs font-semibold sm:text-sm">
                                    <li class="flex items-center gap-2.5"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20"><?= icon('check', 'size-3.5') ?></span>Zero-auth TSV copy for instant <code>Ctrl/⌘ + V</code> into Google Sheets</li>
                                    <li class="flex items-center gap-2.5"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20"><?= icon('check', 'size-3.5') ?></span>Formula-injection safe UTF-8 BOM CSV exports for Excel</li>
                                    <li class="flex items-center gap-2.5"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20"><?= icon('check', 'size-3.5') ?></span>Complete JSON workspace export &amp; one-click sample data reset</li>
                                </ul>
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="rounded-3xl bg-note p-5 text-noteink shadow-sm">
                                    <div class="flex items-center justify-between">
                                        <span class="inline-flex items-center gap-1.5 text-xs font-extrabold"><?= icon('pin', 'size-4') ?>Pinned Studio Note</span>
                                        <span class="text-[10px] font-bold opacity-70">Sidebar sync</span>
                                    </div>
                                    <p class="mt-3 text-xs leading-relaxed">
                                        “Reels hook formula: first 2 seconds show the movement + bold 4-word text overlay. Keep captions scannable with 3 bullet takeaways.”
                                    </p>
                                </div>
                                <div class="rounded-3xl border border-line bg-soft2 p-5">
                                    <div class="flex items-center justify-between">
                                        <span class="chip bg-pink-100 text-pink-700 dark:bg-pink-500/20 dark:text-pink-300"><?= icon('tag', 'size-3.5') ?>Hashtag Set</span>
                                        <button type="button" class="btn-soft !px-3.5 !py-2 !text-xs" @click="$copy('#contentplanner #socialmediamanager #contentstrategy #creatortools')">
                                            <?= icon('copy', 'size-3.5') ?>Try Copy
                                        </button>
                                    </div>
                                    <h4 class="mt-2.5 text-sm font-extrabold">Core Brand Hashtags</h4>
                                    <p class="mt-1 font-mono text-[11px] leading-relaxed text-muted">#contentplanner #socialmediamanager #contentstrategy #creatortools</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============ Complete Feature Grid ============ -->
            <section id="features" class="mx-auto max-w-5xl scroll-mt-20 py-10">
                <div class="mx-auto max-w-3xl text-center">
                    <span class="chip bg-brandsoft text-brand">Complete Feature Set</span>
                    <h2 class="mt-3 text-2xl font-extrabold tracking-tight sm:text-3xl">Everything You Need to Run a Content Studio</h2>
                    <p class="mt-2 text-sm text-muted sm:text-base">Every module in Content Planner is crafted for speed, clarity, and zero external dependencies.</p>
                </div>

                <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <?php foreach ($features as $f): ?>
                        <article class="card flex flex-col gap-3 p-5 transition hover:-translate-y-0.5 hover:shadow-lg">
                            <div class="flex items-center justify-between">
                                <span class="grid size-11 place-items-center rounded-2xl <?= $f['color'] ?>"><?= icon($f['icon'], 'size-5.5') ?></span>
                                <code class="rounded-full bg-soft px-2.5 py-1 text-[11px] font-bold text-muted"><?= e($f['badge']) ?></code>
                            </div>
                            <h3 class="mt-1 text-base font-extrabold"><?= e($f['title']) ?></h3>
                            <p class="text-xs leading-relaxed text-muted sm:text-sm"><?= e($f['desc']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- ============ Security Architecture & PHP2Shell Protection ============ -->
            <section id="security" class="mx-auto max-w-5xl scroll-mt-20 py-10">
                <div class="overflow-hidden rounded-[1.75rem] bg-linear-to-br from-hero to-hero2 p-6 text-white shadow-card sm:p-8 lg:rounded-[2rem]">
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-center">
                        <div>
                            <span class="chip bg-white/15 text-emerald-300"><?= icon('shield', 'size-3.5') ?>Security-First Architecture</span>
                            <h2 class="mt-3 text-2xl font-extrabold leading-tight tracking-tight sm:text-3xl">
                                Built with Physical Multi-User Isolation &amp; PHP2Shell Protection
                            </h2>
                            <p class="mt-3 text-sm leading-relaxed text-white/80">
                                Unlike traditional multi-tenant apps that mix every user’s rows in a single shared table, Content Planner gives each user a physically separate SQLite database file (<code>data/users/{id}.sqlite</code>) and enforces a runtime patched against the critical <b>PHP2Shell</b> vulnerabilities.
                            </p>
                            <div class="mt-5 rounded-2xl bg-white/15 p-4 text-xs leading-relaxed text-white/90">
                                <div class="font-extrabold text-emerald-300">Minimum Patched PHP Versions Enforced:</div>
                                <div class="mt-2 grid grid-cols-2 gap-2 font-mono text-[11px]">
                                    <span class="rounded-lg bg-slate-900/80 px-3 py-1.5">PHP 8.2 &ge; 8.2.35</span>
                                    <span class="rounded-lg bg-slate-900/80 px-3 py-1.5">PHP 8.3 &ge; 8.3.36</span>
                                    <span class="rounded-lg bg-slate-900/80 px-3 py-1.5">PHP 8.4 &ge; 8.4.27</span>
                                    <span class="rounded-lg bg-slate-900/80 px-3 py-1.5">PHP 8.5 &ge; 8.5.12</span>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <?php foreach ($securityItems as $s): ?>
                                <div class="rounded-3xl bg-white/15 p-5">
                                    <span class="grid size-10 place-items-center rounded-2xl bg-white/20 text-emerald-300"><?= icon($s['icon'], 'size-5') ?></span>
                                    <h3 class="mt-3 text-base font-extrabold"><?= e($s['title']) ?></h3>
                                    <p class="mt-1.5 text-xs leading-relaxed text-white/85"><?= e($s['desc']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============ Quick Start & Local Development Guide ============ -->
            <section id="quickstart" class="mx-auto max-w-5xl scroll-mt-20 py-10">
                <div class="mx-auto max-w-3xl text-center">
                    <span class="chip bg-brandsoft text-brand">Quick Start &amp; Self-Hosting</span>
                    <h2 class="mt-3 text-2xl font-extrabold tracking-tight sm:text-3xl">Up and Running in 60 Seconds</h2>
                    <p class="mt-2 text-sm text-muted sm:text-base">
                        Prebuilt CSS is included at <code>public/assets/app.css</code>, so <b>Node.js is not required</b> to run the app. Run it with <code>php -S</code>, XAMPP, Laragon, Laravel Herd, or Apache.
                    </p>
                </div>

                <div class="mt-8 grid grid-cols-1 gap-5 lg:grid-cols-2">
                    <!-- Steps Column -->
                    <div class="card flex flex-col gap-4 p-5 sm:p-6">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-lg font-extrabold">Installation Steps</h3>
                            <div class="inline-flex rounded-full bg-soft p-1 text-xs font-bold" role="tablist">
                                <button type="button" @click="installTab = 'cli'" :class="installTab === 'cli' ? 'bg-hero text-white' : 'text-muted hover:text-ink'" class="rounded-full px-3 py-1.5 transition">php -S (CLI)</button>
                                <button type="button" @click="installTab = 'stack'" :class="installTab === 'stack' ? 'bg-hero text-white' : 'text-muted hover:text-ink'" class="rounded-full px-3 py-1.5 transition">XAMPP / Laragon / Herd</button>
                            </div>
                        </div>

                        <!-- Step 1: Clone -->
                        <div>
                            <div class="mb-1 flex items-center justify-between text-xs font-bold">
                                <span>1. Clone the repository from GitHub</span>
                                <button type="button" class="text-brand hover:underline" @click="$copy('git clone https://github.com/rikiag/content-planner.git\ncd content-planner')">Copy</button>
                            </div>
                            <pre class="overflow-x-auto rounded-2xl bg-slate-900 p-3.5 font-mono text-xs leading-relaxed text-white"><code>git clone https://github.com/rikiag/content-planner.git
cd content-planner</code></pre>
                        </div>

                        <!-- Step 2: Config local -->
                        <div>
                            <div class="mb-1 flex items-center justify-between text-xs font-bold">
                                <span>2. Set up local configuration (optional overrides)</span>
                                <button type="button" class="text-brand hover:underline" @click="$copy('cp config.example.php config.local.php')">Copy</button>
                            </div>
                            <pre class="overflow-x-auto rounded-2xl bg-slate-900 p-3.5 font-mono text-xs leading-relaxed text-white"><code>cp config.example.php config.local.php</code></pre>
                        </div>

                        <!-- Step 3: Run -->
                        <div x-show="installTab === 'cli'">
                            <div class="mb-1 flex items-center justify-between text-xs font-bold">
                                <span>3. Start with PHP’s built-in web server</span>
                                <button type="button" class="text-brand hover:underline" @click="$copy('php -S localhost:8000 -t public public/index.php')">Copy</button>
                            </div>
                            <pre class="overflow-x-auto rounded-2xl bg-slate-900 p-3.5 font-mono text-xs leading-relaxed text-white"><code>php -S localhost:8000 -t public public/index.php</code></pre>
                            <p class="mt-2 text-xs text-muted">Then open <code>http://localhost:8000</code> in your browser and create your first account (keep <b>“Start with sample data”</b> checked to explore right away).</p>
                        </div>

                        <div x-show="installTab === 'stack'" x-cloak>
                            <div class="mb-1 text-xs font-bold">3. Run with XAMPP, Laragon, Laravel Herd, or Apache</div>
                            <div class="rounded-2xl bg-soft2 p-4 text-xs leading-relaxed text-ink">
                                <ul class="flex flex-col gap-2">
                                    <li>• <b>XAMPP / Apache subdirectory:</b> Clone into <code>htdocs/content-planner</code> or <code>/var/www/html/content-planner</code> (ensure <code>mod_rewrite</code> + <code>AllowOverride All</code> are enabled) and open <code>http://localhost/content-planner/</code>.</li>
                                    <li>• <b>Laragon / Laravel Herd:</b> Clone into <code>C:\laragon\www\content-planner</code> or <code>~/Herd/content-planner</code> and open <code>http://content-planner.test</code> (or point VirtualHost DocumentRoot to <code>public/</code>).</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Requirements & Architecture Column -->
                    <div class="flex flex-col gap-5">
                        <div class="card p-5 sm:p-6">
                            <h3 class="text-base font-extrabold">System Requirements</h3>
                            <ul class="mt-4 flex flex-col gap-3 text-xs sm:text-sm">
                                <li class="flex items-start gap-3">
                                    <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20"><?= icon('check', 'size-3.5') ?></span>
                                    <div>
                                        <b class="block text-ink">PHP Patched Against PHP2Shell</b>
                                        <span class="text-xs text-muted">PHP <code>&ge; 8.2.35</code>, <code>&ge; 8.3.36</code>, <code>&ge; 8.4.27</code>, or <code>&ge; 8.5.12</code></span>
                                    </div>
                                </li>
                                <li class="flex items-start gap-3">
                                    <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20"><?= icon('check', 'size-3.5') ?></span>
                                    <div>
                                        <b class="block text-ink">Required PHP Extensions</b>
                                        <span class="text-xs text-muted"><code>pdo_sqlite</code> and <code>mbstring</code> enabled</span>
                                    </div>
                                </li>
                                <li class="flex items-start gap-3">
                                    <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20"><?= icon('check', 'size-3.5') ?></span>
                                    <div>
                                        <b class="block text-ink">Writable <code>data/</code> Directory</b>
                                        <span class="text-xs text-muted">Auto-creates <code>auth.sqlite</code>, <code>users/{id}.sqlite</code>, <code>sessions/</code>, and <code>logs/</code></span>
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <div class="card p-5 sm:p-6">
                            <h3 class="text-base font-extrabold">Project Structure</h3>
                            <pre class="mt-3 overflow-x-auto rounded-2xl bg-soft2 p-3.5 font-mono text-[11px] leading-relaxed text-ink"><code>public/      index.php, sw.js, manifest, assets/, icons/
src/         config, db, auth, helpers, layout, forms, seed
src/pages/   landing, auth, home, content, campaign, idea,
             resources, analytics, account, notes, export
src/css/     app.css (Tailwind CSS 4 source)
data/        auth.sqlite, users/{id}.sqlite, sessions/, logs/
config.example.php -> config.local.php</code></pre>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============ Routes Reference ============ -->
            <section id="routes" class="mx-auto max-w-5xl scroll-mt-20 py-10">
                <div class="card overflow-hidden p-5 sm:p-8">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <span class="chip bg-brandsoft text-brand">Application Routes</span>
                            <h2 class="mt-2 text-xl font-extrabold sm:text-2xl">Clean URL Routing &amp; Endpoints</h2>
                        </div>
                        <a href="<?= e($demoUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn-soft !text-xs">
                            <?= icon('external', 'size-4') ?><span>Explore Live Demo</span>
                        </a>
                    </div>

                    <div class="mt-5 overflow-x-auto">
                        <table class="w-full min-w-[34rem] text-left text-xs sm:text-sm">
                            <thead class="border-b border-line text-[11px] uppercase tracking-wider text-muted">
                                <tr>
                                    <th class="pb-3 pr-3 font-extrabold">Route</th>
                                    <th class="pb-3 font-extrabold">Description</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                <?php foreach ($routesList as [$rt, $desc]): ?>
                                    <tr>
                                        <td class="whitespace-nowrap py-3 pr-3 font-mono text-xs font-bold text-brand"><?= e($rt) ?></td>
                                        <td class="py-3 text-muted"><?= e($desc) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ============ Free & Open Source (MIT License) ============ -->
            <section id="license" class="mx-auto max-w-5xl scroll-mt-20 py-10">
                <div class="card border border-line p-6 sm:p-8">
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-center">
                        <div>
                            <span class="chip bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">
                                <?= icon('github', 'size-3.5') ?><span>Free &amp; Open Source · MIT License</span>
                            </span>
                            <h2 class="mt-3 text-2xl font-extrabold tracking-tight">
                                Permissive, Self-Hostable &amp; Community Friendly
                            </h2>
                            <p class="mt-2.5 text-sm leading-relaxed text-muted">
                                Content Planner is released under the <b>MIT License</b> — giving individuals, creators, agencies, and enterprises maximum freedom to use, self-host, customize, and distribute the software with minimal legal friction, as outlined in
                                <a href="<?= e($blogLicenseUrl) ?>" target="_blank" rel="noopener noreferrer" class="font-bold text-brand hover:underline">MIT, GPL, Apache 2.0, atau Proprietary: Memilih Lisensi yang Tepat untuk Proyekmu</a>.
                            </p>
                            <div class="mt-5 flex flex-wrap gap-2.5">
                                <a href="<?= e($ghUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn-dark">
                                    <?= icon('github', 'size-4.5') ?><span>Star &amp; Fork on GitHub</span>
                                </a>
                                <a href="<?= e($ghUrl . '/blob/main/LICENSE') ?>" target="_blank" rel="noopener noreferrer" class="btn-ghost">
                                    <?= icon('file', 'size-4') ?><span>Read MIT License</span>
                                </a>
                                <?php if (!$user): ?>
                                    <a href="<?= e(url('/register')) ?>" class="btn-primary">
                                        <?= icon('sparkle', 'size-4') ?><span>Create Free Account</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="rounded-3xl bg-soft2 p-5 font-mono text-xs leading-relaxed text-muted">
                            <div class="font-bold text-ink">MIT License</div>
                            <div class="mt-1">Copyright (c) 2026 Riki Agusnaidi (rikiag)</div>
                            <p class="mt-3 text-[11px] leading-relaxed">
                                Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, subject to including the copyright notice and permission notice.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- ============ Footer ============ -->
        <footer class="border-t border-line bg-surface/90 py-8">
            <div class="mx-auto flex max-w-[1500px] flex-col items-center justify-between gap-4 px-4 text-center text-xs text-muted sm:flex-row sm:px-6 sm:text-left">
                <div class="flex items-center gap-2.5">
                    <span class="grid size-8 place-items-center rounded-xl bg-linear-to-br from-blue-500 to-indigo-600 text-white"><?= icon('calendar-check', 'size-4.5') ?></span>
                    <span>
                        <strong class="font-extrabold text-ink"><?= e(APP_NAME) ?></strong> · Built by
                        <a href="https://rikiag.com" target="_blank" rel="noopener noreferrer" class="font-bold text-brand hover:underline">Riki Agusnaidi (rikiag)</a>
                    </span>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-4 font-semibold">
                    <a href="<?= e($demoUrl) ?>" target="_blank" rel="noopener noreferrer" class="hover:text-ink">Live Demo</a>
                    <a href="<?= e($ghUrl) ?>" target="_blank" rel="noopener noreferrer" class="hover:text-ink">GitHub</a>
                    <a href="<?= e($blogLicenseUrl) ?>" target="_blank" rel="noopener noreferrer" class="hover:text-ink">License Guide</a>
                    <?php if ($user): ?>
                        <a href="<?= e(url('/')) ?>" class="text-brand hover:underline">Dashboard</a>
                    <?php else: ?>
                        <a href="<?= e(url('/login')) ?>" class="hover:text-ink">Sign In</a>
                        <a href="<?= e(url('/register')) ?>" class="text-brand hover:underline">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </footer>

        <?php layout_overlays($flashes); ?>
    </body>

    </html>
<?php
}
