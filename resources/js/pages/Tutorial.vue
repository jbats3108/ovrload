<script setup lang="ts">
import BrandName from '@/components/BrandName.vue';
import PublicSiteHeader from '@/components/PublicSiteHeader.vue';
import TutorialShot from '@/components/TutorialShot.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const isSignedIn = computed(() => Boolean(page.props.auth.user));

const toc = [
    { href: '#install', label: 'Install on your phone' },
    { href: '#oriented', label: 'Get oriented' },
    { href: '#training', label: 'Training preferences' },
    { href: '#profiles', label: 'Exercise profiles' },
    { href: '#editor', label: 'Create a routine' },
    { href: '#play', label: 'Play a workout' },
    { href: '#bump', label: 'Bumps' },
    { href: '#deload', label: 'Deloads' },
    { href: '#afterward', label: 'After you finish' },
    { href: '#features', label: 'Features' },
] as const;
</script>

<template>
    <Head title="Tutorial">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <div class="tutorial relative min-h-dvh bg-background text-foreground">
        <div class="tutorial-atmosphere pointer-events-none absolute inset-0" aria-hidden="true" />

        <PublicSiteHeader />

        <main class="relative z-10 mx-auto w-full max-w-2xl px-6 pb-20 sm:px-10">
            <p class="text-sm font-medium tracking-widest text-primary uppercase">Guide</p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">How to use <BrandName /></h1>
            <p class="mt-3 text-muted-foreground">
                You plan a routine, play it at the gym, and raise working weights when a session earns a bump. This page walks that loop.
            </p>

            <nav
                class="sticky top-0 z-20 -mx-6 mt-8 border-y border-border bg-background/90 px-6 py-3 backdrop-blur-sm sm:-mx-10 sm:px-10"
                aria-label="On this page"
            >
                <ul class="flex flex-wrap gap-x-4 gap-y-2 text-sm">
                    <li v-for="item in toc" :key="item.href">
                        <a :href="item.href" class="text-primary underline-offset-2 hover:underline">{{ item.label }}</a>
                    </li>
                </ul>
            </nav>

            <section id="install" class="mt-12 scroll-mt-20 space-y-3">
                <h2 class="text-2xl font-bold tracking-tight">Install on your phone</h2>
                <p class="text-muted-foreground">
                    <BrandName /> installs from the browser — no App Store or Play Store. Add it to your home screen for a full-screen icon, quieter
                    browser chrome, and a better gym session.
                </p>
                <ul class="list-disc space-y-4 pl-5 text-muted-foreground">
                    <li>
                        <span class="font-medium text-foreground">iPhone / iPad (Safari)</span>
                        <p class="mt-1">
                            Open the site in Safari, tap Share, then <span class="font-medium text-foreground">Add to Home Screen</span>. On iOS
                            Safari you may also see an install tip near the bottom of the app until you dismiss it.
                        </p>
                    </li>
                    <li>
                        <span class="font-medium text-foreground">Android (Chrome)</span>
                        <p class="mt-1">
                            Open the site in Chrome, tap the menu (⋮), then <span class="font-medium text-foreground">Install app</span> or
                            <span class="font-medium text-foreground">Add to Home screen</span>.
                        </p>
                    </li>
                </ul>
                <p class="text-muted-foreground">
                    After install, open <BrandName /> from the home-screen icon rather than a browser tab when you train.
                </p>
            </section>

            <section id="oriented" class="mt-12 scroll-mt-20 space-y-3">
                <h2 class="text-2xl font-bold tracking-tight">Get oriented</h2>
                <p class="text-muted-foreground">
                    After you log in, the app is a few tabs: <strong class="text-foreground">Dashboard</strong> (your routines),
                    <strong class="text-foreground">History</strong> (finished sessions),
                    <strong class="text-foreground">Preferences</strong> (training and appearance),
                    <strong class="text-foreground">Account</strong> (profile and password), and, for admins,
                    <strong class="text-foreground">Admin</strong>. On a phone those live in the bottom bar; on a wider screen they sit in the
                    sidebar.
                </p>
                <p class="text-muted-foreground">
                    Dashboard cards start a workout, open the editor, duplicate, or delete. If a session is already in progress, finish or abandon it
                    before you start another.
                </p>
                <TutorialShot
                    name="oriented"
                    alt="Dashboard with routines, Start and Deload, and navigation"
                    caption="Dashboard: routines, Start / Deload, and (on a phone) the bottom tabs."
                />
                <p v-if="isSignedIn">
                    <Link :href="route('dashboard')" class="font-medium text-primary underline-offset-2 hover:underline">Open your dashboard</Link>
                </p>
            </section>

            <section id="training" class="mt-12 scroll-mt-20 space-y-3">
                <h2 class="text-2xl font-bold tracking-tight">Set your training preferences</h2>
                <p class="text-muted-foreground">
                    Open <strong class="text-foreground">Preferences</strong> before you build a lot of routines. The
                    <strong class="text-foreground">Exercise profiles</strong> section manages reusable Profile Details: Target, Floor, working Rest,
                    and warm-up steps. The separate warm-up placement setting controls whether a selected profile’s ladder seeds every new exercise or
                    only the first one.
                </p>
                <p class="text-muted-foreground">
                    <strong class="text-foreground">Progression style</strong> controls mid-session ramping and finish bumps:
                    <strong class="text-foreground">Straight Sets</strong> keeps the same weight for every working set and offers a finish bump if any
                    set hit Target; <strong class="text-foreground">Progressive Overload</strong> can raise the next set by 2.5 kg when Target is hit
                    (ask on rest or auto) and offers a finish bump only when the final working set was at your session top weight and hit Target.
                    Deload defaults and your bar/plate inventory live here too; the plate guide in Play uses that inventory.
                </p>
                <p v-if="isSignedIn">
                    <Link :href="route('training.edit')" class="font-medium text-primary underline-offset-2 hover:underline">Open Preferences</Link>
                </p>
            </section>

            <section id="profiles" class="mt-12 scroll-mt-20 space-y-3">
                <h2 class="text-2xl font-bold tracking-tight">Choose an exercise profile</h2>
                <p class="text-muted-foreground">
                    When you create a routine, choosing a profile is required in the first step. Your
                    <strong class="text-foreground">default profile</strong> is preselected. If you have not chosen one yet, pick an
                    <BrandName class="mr-1" />preset or create your own custom profile.
                </p>
                <p class="text-muted-foreground">
                    A profile gives each exercise an exact <strong class="text-foreground">Target</strong>. Its
                    <strong class="text-foreground">Floor</strong> starts two reps lower, creating an effective Floor-to-Target range; you can
                    explicitly override Floor when needed. Profiles also carry working Rest and a complete warm-up ladder. Warm-up steps can be a
                    percent of working weight, your empty bar, or a fixed kg — useful when a 20&nbsp;kg bar does not suit a lift like deadlift.
                </p>
                <ul class="list-disc space-y-2 pl-6 text-muted-foreground">
                    <li><BrandName class="mr-1" />Strength — Target 6, Floor 4, 3-minute working Rest; Warm-up bar×10, 50%×5, 75%×3, 90%×1.</li>
                    <li><BrandName class="mr-1" />Hypertrophy — Target 10, Floor 8, 90-second working Rest; Warm-up 50%×10, 80%×5.</li>
                    <li><BrandName class="mr-1" />Endurance — Target 17, Floor 15, 1-minute working Rest; Warm-up 50%×10, 75%×5.</li>
                </ul>
                <p class="text-muted-foreground">
                    In the routine editor, each exercise has a profile selector. Selecting a different profile copies its Profile Details into that
                    exercise. Target, Floor, Rest, and warm-up stay collapsed until you choose Custom (or Customize). Editing a profile-owned value
                    makes that block or exercise
                    <strong class="text-foreground">Custom</strong>; saving those Profile Details as a profile is explicit.
                </p>
                <p class="text-muted-foreground">
                    Supersets can use a different profile for A and B. Their Target/Floor values are separate, but warm-ups and working Rest are
                    shared by the pair. Changing a profile does not rewrite existing blocks. You can explicitly choose to update eligible existing
                    uses after editing a custom profile. Custom profiles in use cannot be deleted or archived until you choose a different profile in
                    those routines. Preferences lists a routine when that profile is the routine profile or selected on an exercise — leftover rest or
                    warm-up links after switching an exercise to Custom do not count.
                </p>
                <TutorialShot
                    name="training"
                    alt="Preferences showing exercise profiles and training settings"
                    caption="Preferences: choose a default, manage custom profiles, and review OVRLOAD preset Profile Details."
                />
                <p v-if="isSignedIn">
                    <Link :href="route('training.edit')" class="font-medium text-primary underline-offset-2 hover:underline">
                        Manage exercise profiles
                    </Link>
                </p>
            </section>

            <section id="editor" class="mt-12 scroll-mt-20 space-y-3">
                <h2 class="text-2xl font-bold tracking-tight">Create and edit a routine</h2>
                <p class="text-muted-foreground">
                    Choose a profile before naming the routine. A routine is a list of exercises (internally, blocks). Each exercise has working sets
                    and a profile. On mobile, the first tab is a
                    <strong class="text-foreground">Routine</strong> sheet (name, routine profile, Deload); exercise tabs come after. On desktop, the
                    same routine-level controls live in a collapsed <strong class="text-foreground">Routine settings</strong> strip under the title.
                    Target, Floor, Rest, warm-ups, and Deload Alternate stay hidden while a profile is selected — choose
                    <strong class="text-foreground">Custom settings</strong> (or Customize) to override them. Optional dropsets are per exercise. Pick
                    lifts from the catalog, or add a private custom that only you see. Per-exercise
                    <strong class="text-foreground">Deload Alternate</strong> is covered under Deloads below.
                </p>
                <p class="text-muted-foreground">
                    <strong class="text-foreground">Setup</strong> is a pause so you can load the bar or walk to a machine. You can put setup before
                    the working sets, after warm-ups, or both. For a <strong class="text-foreground">superset</strong>, you pair two exercises in one
                    block — Play will flip between them each round. Use the superset controls in the editor to add or split that pair.
                </p>
                <TutorialShot
                    name="editor"
                    alt="Routine editor with profile selectors, setup options, and deload alternate"
                    caption="Editor: each exercise has a profile selector; Target and Floor appear when you choose Custom (a superset can still differ)."
                />
                <p v-if="isSignedIn">
                    <Link :href="route('routines.create')" class="font-medium text-primary underline-offset-2 hover:underline">Create a routine</Link>
                </p>
            </section>

            <section id="play" class="mt-12 scroll-mt-20 space-y-3">
                <h2 class="text-2xl font-bold tracking-tight">Play a workout</h2>
                <p class="text-muted-foreground">
                    From a routine card, start a normal session or a Deload. Play walks you through setup, warm-ups, working sets, and rest. The
                    header shows which set you are on. Done opens the log sheet; Log set writes the weight and reps; Cancel backs out without saving
                    that set.
                </p>
                <p class="text-muted-foreground">
                    Rest counts down with ticks near the end. Skip rest if you need to. You can add or remove incomplete working sets mid-session —
                    that edits this workout, not the routine. On a working set you can also
                    <strong class="text-foreground">Promote to dropset</strong> or <strong class="text-foreground">Demote to single</strong> before
                    you log it. Keep the tab open if you can; wake lock tries to stop the phone sleeping.
                </p>
                <p class="text-muted-foreground">
                    Need an extra lift? Tap <strong class="text-foreground">Add exercise to session</strong> in the header, under Finish / Abandon /
                    Leave. Choose a catalog or private custom exercise and it is appended as a single, three-set block on this workout only. It does
                    not change the routine or offer progression; its Target and working Rest come from your user default exercise profile. You can
                    remove the new block before logging a set.
                </p>
                <p class="text-muted-foreground">
                    On the log sheet you will see <strong class="text-foreground">Floor</strong> and
                    <strong class="text-foreground">Bump @</strong> (your Target reps). Hitting Target at working weight is what unlocks a bump — not
                    the Floor.
                </p>
                <TutorialShot
                    name="play"
                    alt="Play screen with Add exercise to session in the header under Finish, Abandon, and Leave"
                    caption="Play: Add exercise to session spans the header under Finish / Abandon / Leave; set actions (+/− Set, Promote/Demote dropset) and Skip rest of group stay on the stage; Done opens the log sheet."
                />
            </section>

            <section id="bump" class="mt-12 scroll-mt-20 space-y-3">
                <h2 class="text-2xl font-bold tracking-tight">What a bump is</h2>
                <p class="text-muted-foreground">
                    A <strong class="text-foreground">bump</strong> is a confirmed increase to that exercise’s
                    <strong class="text-foreground">working weight on the routine</strong> — the load the next standard session will prescribe. It is
                    progressive overload, one lift at a time. The app never silently adds plates; you tick the lifts you want on the finish screen.
                </p>
                <p class="text-muted-foreground">
                    You earn a <strong class="text-foreground">finish bump</strong> when you hit the exercise’s prescribed
                    <strong class="text-foreground">Target</strong> reps at (or above) the snapshotted working weight. Which set counts depends on
                    your <strong class="text-foreground">Progression style</strong> in Preferences (snapshotted when the workout starts):
                </p>
                <ul class="list-disc space-y-2 pl-6 text-muted-foreground">
                    <li>
                        <strong class="text-foreground">Straight Sets</strong> (default) — same weight for every working set in the block. A finish
                        bump is offered if <em>any</em> working set at that weight hit Target.
                    </li>
                    <li>
                        <strong class="text-foreground">Progressive Overload</strong> — when a working set hits Target, the <em>next</em> set can go
                        up by 2.5 kg (Ask on rest, or Auto-bump next set). Logged weight always wins over the suggested bump. A finish bump is offered
                        only if your <em>final</em> working set in the block was at the session’s top weight and hit Target — backing off on later
                        sets (e.g. 85 → 82.5 → 82.5) does not earn a finish bump even if an earlier set was heavy.
                    </li>
                </ul>
                <p class="text-muted-foreground">
                    Mid-block bumps only change what Play prefills for the next set; they do not update the routine until you finish. Say yes to a
                    finish bump and next time that lift starts heavier. Say no, and the routine stays put (carry-forward can still raise it if you
                    already lifted more than the prescribed weight).
                </p>
                <p class="text-muted-foreground">
                    <strong class="text-foreground">Carry-forward</strong> is the quiet cousin: finishing a standard session sets the routine to the
                    highest achieved top weight from that workout, without asking, and only ever up. A finish bump is the extra +2.5 kg step on top
                    when you hit Target under your progression style. Deload sessions do neither.
                </p>
            </section>

            <section id="deload" class="mt-12 scroll-mt-20 space-y-3">
                <h2 class="text-2xl font-bold tracking-tight">What a deload is</h2>
                <p class="text-muted-foreground">
                    A <strong class="text-foreground">Deload</strong> is the same routine, started lighter — not a second programme. From the routine
                    card you start <strong class="text-foreground">standard</strong> or Deload. Deload applies that routine’s
                    <strong class="text-foreground">settings</strong> (a weight factor and a reps factor, the same for every lift) to the snapshot.
                    Warm-ups are omitted; the working weights are already light.
                </p>
                <p class="text-muted-foreground">
                    Preferences holds the defaults new routines copy. In the editor, above the exercise list, each routine can set its own factors and
                    <strong class="text-foreground">Deload Velocity</strong> — how many finished standard sessions on that routine before Dashboard
                    softly hints at a Deload. Set velocity to 0 to never hint (handy for rare or one-off routines). The hint is not a calendar and it
                    does not start the session for you.
                </p>
                <p class="text-muted-foreground">
                    Optional <strong class="text-foreground">Deload Alternate</strong> on an exercise swaps in a different lift for Deload only, with
                    its own working weight used as-is (the weight factor does not scale that alternate). Prescribed reps still come from the primary
                    via the profile settings. If an alternate is set, that lift’s Deload snapshot is singles — no dropsets. Finishing a Deload does
                    not bump or carry-forward your usual working weights.
                </p>
            </section>

            <section id="afterward" class="mt-12 scroll-mt-20 space-y-3">
                <h2 class="text-2xl font-bold tracking-tight">After you finish</h2>
                <p class="text-muted-foreground">
                    Finished sessions land in History. Editing working weight or reps on the latest non-deload finish can re-run progression — it may
                    offer bumps again, or let you undo a bump you already confirmed. Add a historical workout if you trained without the phone.
                    Dashboard shows a short strip of recent finishes.
                </p>
                <TutorialShot
                    name="afterward"
                    alt="History list of finished workouts"
                    caption="History: finished sessions. Add historical if you trained without the phone."
                />
                <p v-if="isSignedIn" class="flex flex-wrap gap-x-4 gap-y-2">
                    <Link :href="route('history.index')" class="font-medium text-primary underline-offset-2 hover:underline">Open History</Link>
                    <Link :href="route('dashboard')" class="font-medium text-primary underline-offset-2 hover:underline">Back to Dashboard</Link>
                </p>
            </section>

            <section id="features" class="mt-12 scroll-mt-20 space-y-4">
                <h2 class="text-2xl font-bold tracking-tight">Features in brief</h2>
                <ul class="space-y-4 text-muted-foreground">
                    <li>
                        <p class="font-medium text-foreground">Plate guide</p>
                        <p class="mt-1">For barbell and EZ-bar lifts, Play shows the nearest loadable stack from your Preferences plate profile.</p>
                    </li>
                    <li>
                        <p class="font-medium text-foreground">Supersets</p>
                        <p class="mt-1">Two exercises share a block. You complete a round of A then B, with the rest you set on that block.</p>
                    </li>
                    <li>
                        <p class="font-medium text-foreground">Dropsets</p>
                        <p class="mt-1">A working slot can be several segments at dropping weights. Log each segment on the sheet.</p>
                    </li>
                    <li>
                        <p class="font-medium text-foreground">Deload start</p>
                        <p class="mt-1">
                            Same routine, started lighter from the card. Deload settings scale weight and reps; optional alternate lift; no bump or
                            carry-forward. See <a href="#deload" class="font-medium text-primary underline-offset-2 hover:underline">Deloads</a>.
                        </p>
                    </li>
                    <li>
                        <p class="font-medium text-foreground">Custom exercises</p>
                        <p class="mt-1">Add a private lift from the routine picker. It stays on your account and never joins the shared catalog.</p>
                    </li>
                    <li>
                        <p class="font-medium text-foreground">Data export</p>
                        <p class="mt-1">Account lets you download or delete your account data when you need a copy or a way out.</p>
                    </li>
                </ul>
            </section>

            <section class="cta-panel mt-12 rounded-md border-2 border-primary bg-primary/15 px-5 py-7 sm:px-7" aria-labelledby="next-heading">
                <h2 id="next-heading" class="text-2xl font-bold tracking-tight text-primary">Next</h2>
                <template v-if="isSignedIn">
                    <p class="mt-2 text-sm text-foreground/90 sm:text-base">Jump into the app and try the loop once.</p>
                    <p class="mt-5 flex flex-wrap gap-3">
                        <Link
                            :href="route('dashboard')"
                            class="inline-flex rounded-md bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground transition-opacity hover:opacity-90"
                        >
                            Dashboard
                        </Link>
                        <Link
                            :href="route('training.edit')"
                            class="inline-flex rounded-md border border-primary px-6 py-3 text-sm font-semibold text-primary transition-opacity hover:opacity-90"
                        >
                            Preferences
                        </Link>
                        <Link
                            :href="route('routines.create')"
                            class="inline-flex rounded-md border border-primary px-6 py-3 text-sm font-semibold text-primary transition-opacity hover:opacity-90"
                        >
                            Create a routine
                        </Link>
                    </p>
                </template>
                <template v-else>
                    <p class="mt-2 text-sm text-foreground/90 sm:text-base">
                        <BrandName /> is invite-only. Request an invite, or log in if you already have an account.
                    </p>
                    <p class="mt-5 flex flex-wrap gap-3">
                        <Link
                            :href="route('invite-request')"
                            class="inline-flex rounded-md bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground transition-opacity hover:opacity-90"
                        >
                            Request an invite
                        </Link>
                        <Link
                            :href="route('login')"
                            class="inline-flex rounded-md border border-primary px-6 py-3 text-sm font-semibold text-primary transition-opacity hover:opacity-90"
                        >
                            Log in
                        </Link>
                    </p>
                </template>
            </section>
        </main>
    </div>
</template>

<style scoped>
.tutorial-atmosphere {
    background:
        radial-gradient(ellipse 70% 40% at 50% -5%, color-mix(in oklab, var(--primary) 18%, transparent), transparent 70%),
        radial-gradient(ellipse 40% 30% at 90% 60%, color-mix(in oklab, var(--accent) 10%, transparent), transparent 65%);
}

.cta-panel {
    box-shadow:
        0 0 0 1px color-mix(in oklab, var(--primary) 35%, transparent),
        0 12px 40px -16px color-mix(in oklab, var(--primary) 45%, transparent);
}
</style>
