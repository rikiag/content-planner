<?php
declare(strict_types=1);

defined('ROOT') || (http_response_code(403) && exit);

/**
 * Sample demo data. Dates are generated relative to today so the dashboard
 * always looks active: 13 posts this month, 8 published, 1 overdue.
 */
function seed_demo(PDO $pdo, array $profile = []): void
{
    $pdo->beginTransaction();
    write_profile($pdo, $profile, true);

    /* ---- Campaigns ---- */
    $today = new DateTimeImmutable('today');
    $camp = $pdo->prepare('INSERT INTO campaigns(name,goal,status,start_date,end_date,color) VALUES(?,?,?,?,?,?)');
    $camps = [
        ['21-Day Active Challenge', 'Invite 300 new participants to move for 21 consecutive days and share their progress.', 'active', $today->modify('-12 days'), $today->modify('+14 days'), 'orange'],
        ['Q4 Community Classes', 'Fill year-end community class slots and boost repeat registrations.', 'active', $today->modify('-30 days'), $today->modify('+50 days'), 'blue'],
        ['Studio Brand Awareness', 'Introduce instructors and studio vibes to grow local awareness.', 'active', $today->modify('-60 days'), $today->modify('+30 days'), 'green'],
        ['Year-End Promo', 'Year-end membership bundles and gift vouchers.', 'planned', $today->modify('+20 days'), $today->modify('+55 days'), 'purple'],
        ['Guest Instructor Series', 'Collaborative sessions with guest instructors from local communities.', 'done', $today->modify('-90 days'), $today->modify('-40 days'), 'pink'],
    ];
    foreach ($camps as $c) {
        $camp->execute([$c[0], $c[1], $c[2], $c[3]->format('Y-m-d'), $c[4]->format('Y-m-d'), $c[5]]);
    }

    /* ---- This month's content: 8 published + 5 upcoming/draft ---- */
    $monthStart = new DateTimeImmutable('first day of this month 00:00');
    $dayNow = (int) $today->format('j');
    $monthEnd = (int) $monthStart->format('t');
    $ins = $pdo->prepare('INSERT INTO contents(title,platform,format,pillar,status,campaign_id,scheduled_at,published_at,progress,caption,hashtags,views,likes,comments,shares,saves,created_at,updated_at)
                          VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');

    // [title, platform, format, pillar, campaign_id, views, ER%]
    $published = [
        ['5-Minute Daily Mobility Checklist', 'Instagram', 'Carousel', 'Education', 1, 2700, 12.9],
        ['Kickoff! 21-Day Active Challenge', 'Instagram', 'Reels', 'Community', 1, 2400, 12.8],
        ['Meet Our Lead Instructors', 'Instagram', 'Feed', 'Behind the Scenes', 3, 2100, 13.6],
        ['5-Minute Bedtime Stretch Routine', 'TikTok', 'Reels', 'Education', 3, 1800, 13.8],
        ['This Week’s Class Schedule', 'Instagram', 'Story', 'Promotion', 2, 1500, 13.6],
        ['First Batch Member Testimonials', 'Instagram', 'Feed', 'Community', 2, 1200, 11.2],
        ['Healthy Post-Workout Snack Ideas', 'Facebook', 'Carousel', 'Education', 3, 1000, 9.4],
        ['Morning Vibes at the Studio', 'TikTok', 'Reels', 'Behind the Scenes', 3, 800, 10.1],
    ];
    $wkStart = $today->modify('monday this week');
    $wkStartDay = $wkStart->format('Y-m') === $monthStart->format('Y-m') ? (int) $wkStart->format('j') : 1;
    $wkDays = array_values(array_filter([$wkStartDay, $wkStartDay + 2, $wkStartDay + 4], fn($d) => $d >= 1 && $d < $dayNow));
    $pool = $wkStartDay > 1 ? range(1, $wkStartDay - 1) : range(1, max(1, $dayNow));
    $days = [];
    foreach ($published as $i => $_) {
        $days[$i] = $i < count($wkDays) ? $wkDays[$i] : $pool[($i - count($wkDays)) % count($pool)];
    }
    foreach ($published as $i => $p) {
        $day = $days[$i];
        $when = $monthStart->modify('+' . ($day - 1) . ' days')->setTime(7 + ($i % 4) * 3, 0)->format('Y-m-d H:i');
        $inter = (int) round($p[5] * $p[6] / 100);
        $likes = (int) round($inter * .68);
        $com = (int) round($inter * .08);
        $sh = (int) round($inter * .10);
        $sv = $inter - $likes - $com - $sh;
        $ins->execute([
            $p[0], $p[1], $p[2], $p[3], 'published', $p[4], $when, $when, 100,
            'Sample caption for "' . $p[0] . '". Replace with your final copy.', '#movestudio #dailymovement #activehabits',
            $p[5], $likes, $com, $sh, $sv, $when, $when,
        ]);
    }

    $dayAt = fn(int $d, int $h) => $monthStart->modify('+' . (min($d, $monthEnd) - 1) . ' days')->setTime($h, 0)->format('Y-m-d H:i');
    $late = $today->modify('-1 day')->setTime(7, 0)->format('Y-m-d H:i');
    $pending = [
        ['Challenge Week 1 Member Stories', 'Instagram', 'Story', 'Community', 'draft', 1, $late, 83],
        ['Morning Class Recap + Breathing Tips', 'Instagram', 'Reels', 'Education', 'review', 2, $dayAt($dayNow + 1, 10), 70],
        ['4 Desk Stretches for Office Workers', 'Instagram', 'Carousel', 'Education', 'scheduled', 2, $dayAt($dayNow + 3, 9), 100],
        ['Q&A: Avoiding Beginner Workout Injuries', 'TikTok', 'Video', 'Education', 'draft', 3, $dayAt($dayNow + 5, 13), 35],
        ['Next Month Class Lineup Announcement', 'Instagram', 'Feed', 'Promotion', 'idea', 2, $dayAt($dayNow + 8, 8), 10],
    ];
    foreach ($pending as $k => $p) {
        $upd = $today->modify('-' . $k . ' hours')->format('Y-m-d H:i');
        $ins->execute([$p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], null, $p[7], '', '', 0, 0, 0, 0, 0, $upd, $upd]);
    }

    /* ---- 10-week history (for Analytics trends) ---- */
    mt_srand(42);
    $titles = [
        'Morning vs Evening Workouts Explained', 'Essential Warm-Up Movements', 'Member Spotlight of the Month',
        'Instructor Morning Routine', 'Trial Class Pass Promo', 'How to Choose Training Shoes',
        'Neck Relief Stretches for Desk Jobs', 'Favorite Workout Playlist', 'Quick Post-Workout Smoothie Recipe',
        'Beginner Class FAQ', 'Studio Space Before & After', '3-Minute Box Breathing Guide',
    ];
    $plats = ['Instagram', 'Instagram', 'TikTok', 'Facebook', 'YouTube'];
    $fmts = ['Reels', 'Carousel', 'Feed', 'Story', 'Video'];
    for ($w = 1; $w <= 10; $w++) {
        for ($n = 0; $n < 2; $n++) {
            $date = $today->modify('-' . ($w * 7 - $n * 3 - 4) . ' days')->setTime(8 + $n * 4, 0);
            if ($date->format('Y-m') === $monthStart->format('Y-m')) {
                continue;
            }
            $views = mt_rand(600, 2600) + (10 - $w) * 60;
            $er = mt_rand(70, 140) / 10;
            $inter = (int) round($views * $er / 100);
            $likes = (int) round($inter * .66);
            $com = (int) round($inter * .09);
            $sh = (int) round($inter * .11);
            $f = $date->format('Y-m-d H:i');
            $ins->execute([
                $titles[($w + $n * 5) % count($titles)], $plats[mt_rand(0, 4)], $fmts[mt_rand(0, 4)],
                PILLARS[mt_rand(0, 5)], 'published', mt_rand(1, 3), $f, $f, 100, '', '',
                $views, $likes, $com, $sh, $inter - $likes - $com - $sh, $f, $f,
            ]);
        }
    }

    /* ---- Idea Bank ---- */
    $idea = $pdo->prepare('INSERT INTO ideas(title,notes,pillar,priority,status) VALUES(?,?,?,?,?)');
    foreach ([
        ['Mini-series: 5 Common Fitness Myths', 'One myth per slide; ask followers to guess before revealing the answer.', 'Education', 'high', 'new'],
        ['Transition Reel: Desk Setup to Stretch Mat', 'Use an upbeat audio track, keep it under 8 seconds.', 'Entertainment', 'medium', 'new'],
        ['Member Progress Spotlight', 'Ask permission first, include a short quote and progress photo.', 'Community', 'high', 'saved'],
        ['A Day in the Life of an Instructor (POV)', '', 'Behind the Scenes', 'medium', 'saved'],
        ['Story Quiz: Guess the Stretch Name', 'Use the interactive quiz sticker in Stories.', 'Entertainment', 'low', 'saved'],
        ['Holiday Membership Gift Bundles', 'Align with the Year-End Promo campaign.', 'Promotion', 'high', 'saved'],
        ['How to Build a Weekly Movement Habit', 'Turn into a downloadable carousel and template.', 'Education', 'medium', 'used'],
    ] as $i) {
        $idea->execute($i);
    }

    /* ---- Resources ---- */
    $res = $pdo->prepare('INSERT INTO resources(title,type,url,body,tag) VALUES(?,?,?,?,?)');
    foreach ([
        ['Move Studio Brand Guidelines', 'brand', 'https://example.com/brand-guidelines', 'Primary blue #2563EB, accent orange #C2410C. Tone of voice: warm, approachable, encouraging.', 'Brand'],
        ['Photo & Video Asset Folder', 'file', 'https://drive.google.com/', 'Studio photos, instructor headshots, and class B-roll.', 'Assets'],
        ['Caption Template: Call to Action', 'template', '', "Hey {name}! Today we are taking it step by step: {exercise} for {duration}.\nSave this post and tag a friend who needs a movement break! 💪", 'Caption'],
        ['Caption Template: Class Announcement', 'template', '', "📣 New class is now open!\n🗓 {day}, {date} · {time}\n📍 Move Studio\nRegister via the link in bio — limited spots available.", 'Caption'],
        ['Hashtag Set: Daily Fitness', 'hashtag', '', '#movestudio #dailymovement #activehabits #lightworkout #fitnesscommunity #healthyhabits #studioworkout', 'Hashtags'],
        ['Health & Wellness Awareness Calendar', 'link', 'https://example.com/wellness-calendar', 'World Health Day, World Heart Day, and seasonal wellness dates.', 'Reference'],
    ] as $r) {
        $res->execute($r);
    }

    /* ---- Notes ---- */
    $note = $pdo->prepare('INSERT INTO notes(body,color,pinned) VALUES(?,?,?)');
    foreach ([
        ["Content review every Thursday at 3:00 PM.\nMake sure Friday–Sunday posts are scheduled before noon.", 'yellow', 1],
        ['Reels hook formula: first 2 seconds show the movement + bold 4-word text overlay.', 'blue', 1],
        ['Ask Coach Maya for 3 short testimonials from the morning mobility class.', 'green', 0],
    ] as $n) {
        $note->execute($n);
    }

    $pdo->commit();
}

/** Write initial workspace settings (name, studio, targets). */
function write_profile(PDO $pdo, array $p, bool $sample): void
{
    $vals = [
        'name'           => ($p['name'] ?? '') ?: 'User',
        'role'           => ($p['role'] ?? '') ?: 'Social Media Specialist',
        'studio'         => ($p['studio'] ?? '') ?: ($sample ? 'Move Studio' : 'My Studio'),
        'handle'         => ($p['handle'] ?? '') ?: ($sample ? '@movestudio.co' : ''),
        'weekly_target'  => ($p['weekly_target'] ?? '') ?: '4',
        'monthly_target' => ($p['monthly_target'] ?? '') ?: '13',
    ];
    $stmt = $pdo->prepare('INSERT OR REPLACE INTO settings(key,value) VALUES(?,?)');
    foreach ($vals as $k => $v) {
        $stmt->execute([$k, (string) $v]);
    }
}

/** Wipe all workspace records and reload demo data (preserving user profile settings) */
function reset_demo(): void
{
    $pdo = db();
    $profile = [
        'name'           => setting('name', 'User'),
        'role'           => setting('role'),
        'studio'         => setting('studio'),
        'handle'         => setting('handle'),
        'weekly_target'  => setting('weekly_target', '4'),
        'monthly_target' => setting('monthly_target', '13'),
    ];
    $pdo->exec('DELETE FROM contents; DELETE FROM campaigns; DELETE FROM ideas; DELETE FROM resources; DELETE FROM notes; DELETE FROM settings;');
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('contents','campaigns','ideas','resources','notes')");
    seed_demo($pdo, $profile);
}
