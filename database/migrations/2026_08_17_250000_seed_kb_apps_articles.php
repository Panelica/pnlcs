<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The knowledge base shipped empty: a customer clicking Knowledge Base found
 * nothing at all, and the questions the apps feature raises - what am I buying,
 * how many can I run, why is mine not starting, how do I put it on my domain -
 * had no written answer anywhere the customer could reach.
 *
 * Plain text, because the view escapes HTML and only turns newlines into breaks.
 */
return new class extends Migration
{
    private const CATEGORY = 'Apps & Docker';

    private function articles(): array
    {
        return [
            [
                'What am I buying: hosting or an app?',
                <<<'TXT'
You are buying hosting - an amount of memory, CPU, disk and bandwidth. Apps are what you run inside it.

That means there is no separate "Docker product" to buy. Your plan says how much memory and CPU your account has, and every app you install runs inside that. Three apps on a 4 GB plan share 4 GB between them; they do not get 4 GB each.

Two numbers on your plan matter for apps:

- Memory and CPU - the total for everything in your account.
- App limit - the most apps you may have installed at once.

In practice memory is the real limit. A small app might need 128 MB, a large one a gigabyte or more, so how many you can run at the same time depends on which ones you pick.
TXT,
            ],
            [
                'Installing an app',
                <<<'TXT'
Open your service, then the Apps tab.

1. Find the app. There is a search box above the catalogue, and the apps are grouped into sections - Websites, Databases, AI, Developer Tools and so on.
2. Check what it needs. Each card shows the memory the app wants and how many containers it starts. An app that needs more memory than your plan allows is marked before you choose it.
3. Press Install on the card. A box opens underneath where you can give it a name - leave it empty and it is named after the app.

Installing downloads the app, which takes anything from a few seconds to several minutes for larger ones. You can leave the page while it works.

When it is done the app appears in Your Apps with a Running badge, and you can start, stop, restart or remove it from there.
TXT,
            ],
            [
                'Putting an app on your own domain',
                <<<'TXT'
An app is installed inside your account and is not on your domain until you say so.

In the Apps tab, under a running app, pick one of your domains from the list and press "Point here". The domain then serves that app, and its name appears as a link you can open.

To take it back, press the small x next to the domain name. The domain returns to ordinary web hosting and serves your files again.

Two things to know:

- The app has to be running first. A domain cannot point at an app that is stopped or failing to start.
- One domain serves one app. A domain already serving an app does not appear in the list until you unlink it.

If you have no domains on the account yet, add one first - the app has nowhere to go otherwise.
TXT,
            ],
            [
                'My app says "Not starting"',
                <<<'TXT'
That badge means the app starts, fails, and is being restarted over and over. It is not stuck downloading - something is wrong with the app itself.

The two common causes:

Not enough memory. The app needs more than your plan allows, or more than is left after your other apps. Stop or remove another app and try again, or move to a larger plan.

It needs configuration. Some apps will not start until they are given a database, a key, or a settings file. The app's own documentation says which.

What to do:

1. Remove the app and install it again - a failed first start sometimes leaves it in a bad state.
2. Check the memory figure on the app's card against what your plan gives.
3. If neither helps, open a support ticket and say which app it is. We can read its log, which you cannot see from here.
TXT,
            ],
            [
                'How many apps can I run?',
                <<<'TXT'
Two limits apply, and the smaller one wins.

Your plan has an app limit - the counter at the top right of the Apps tab shows it, for example "2 / 5". When it is full the install form disappears until you remove one.

Your plan also has a memory ceiling, and every app draws on it. This is usually the real limit: five apps that each want a gigabyte will not fit in a two gigabyte plan even if your app limit says five.

Some apps count as more than one. An app that also runs a database or a cache starts several containers, and the card says so - "4 containers", for example. They all share your account's memory.

If you are running out of room, either remove something you are not using or upgrade the plan.
TXT,
            ],
            [
                'Removing an app',
                <<<'TXT'
In the Apps tab, press the bin icon next to the app and confirm.

Removing an app deletes it and its data. There is no undo, and the files it kept are not in your backups unless you copied them out yourself first.

If a domain was pointing at the app, that domain goes back to serving your ordinary web hosting.

The memory and the slot in your app limit are freed straight away, so you can install something else immediately.
TXT,
            ],
        ];
    }

    public function up(): void
    {
        $now = now();

        $categoryId = DB::table('kb_categories')->where('name', self::CATEGORY)->value('id');
        if (! $categoryId) {
            $categoryId = DB::table('kb_categories')->insertGetId([
                'parent_id' => null,
                'name' => self::CATEGORY,
                'description' => 'Running applications on your hosting - what they cost you, how to install them and how to put them on your domain.',
                'hidden' => false,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($this->articles() as $i => [$title, $body]) {
            if (DB::table('kb_articles')->where('title', $title)->exists()) {
                continue;
            }
            DB::table('kb_articles')->insert([
                'category_id' => $categoryId,
                'title' => $title,
                'article' => $body,
                'views' => 0,
                'useful' => 0,
                'votes' => 0,
                'private' => false,
                'sort_order' => $i + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $titles = array_column($this->articles(), 0);
        DB::table('kb_articles')->whereIn('title', $titles)->delete();
        DB::table('kb_categories')->where('name', self::CATEGORY)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('kb_articles')->whereColumn('kb_articles.category_id', 'kb_categories.id'))
            ->delete();
    }
};
