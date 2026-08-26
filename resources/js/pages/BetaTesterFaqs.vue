<script setup lang="ts">
import BrandCopy from '@/components/BrandCopy.vue';
import BrandName from '@/components/BrandName.vue';
import PublicSiteHeader from '@/components/PublicSiteHeader.vue';
import { Head, Link } from '@inertiajs/vue3';

const backlogItems = [
    {
        title: 'Add Historical Workouts',
        description:
            "Allow creating a workout that wasn't logged at the time, without needing to run through the entire Play routine. Select a date and time, and enter all the weights you lifted on one screen.",
        recentlyAdded: true,
    },
    {
        title: 'Custom user exercises',
        description:
            'Add your own personal lifts from the routine editor. They stay private to your account — they never show up in anyone else’s catalog.',
        recentlyAdded: true,
    },
    {
        title: 'Add exercise in Play',
        description:
            'Add an extra lift mid-workout from the player — pick from the catalog (or a custom), log it for this session only without changing the routine.',
        recentlyAdded: true,
    },
    {
        title: 'Better History Edits',
        description:
            'Allow editing more details of past workouts, such as warmup weights, and see discarded workouts in history. Bigger structure rewrites on old sessions may come later.',
    },
    {
        title: 'Support for lbs as your preferred unit of weight',
        description:
            "OVRLOAD currently only supports KG as the unit to measure how heavy you're lifting. This will allow you to choose to use LBs instead.",
    },
    {
        title: 'Choose an alternate exercise for Deload sessions',
        description:
            'Pick an optional Deload Alternate (different exercise + its own working weight) on each routine exercise. It only appears on Deload starts; dropsets become singles for that exercise when an alternate is set.',
        recentlyAdded: true,
    },
    {
        title: 'Gym dumbbell / rack inventory',
        description:
            "Let users store all the dumbbell weights at their gym — from smallest to largest — to help with 'run-the-rack' dropsets and planning your routines.",
    },
    {
        title: 'Viewable Progression Data',
        description:
            'Being able to see, and export, your data on how your strength has progressed over time, with charts and tables and spreadsheets, oh my!',
    },
] as const;

function recentlyAdded(item: (typeof backlogItems)[number]): boolean {
    return 'recentlyAdded' in item && item.recentlyAdded === true;
}
</script>

<template>
    <Head title="Beta Tester FAQs">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <div class="faq relative min-h-dvh bg-background text-foreground">
        <div class="faq-atmosphere pointer-events-none absolute inset-0" aria-hidden="true" />

        <PublicSiteHeader current="beta-tester-faqs" />

        <main class="relative z-10 mx-auto w-full max-w-2xl px-6 pb-20 sm:px-10">
            <p class="text-sm font-medium tracking-widest text-primary uppercase">Beta Testers</p>
            <div class="mt-3 space-y-3 text-muted-foreground">
                <p>Hi there! Thank you for considering being a Beta tester for <BrandName />, the workout tracking app for people who lift.</p>
                <p>
                    From this page, you can read some FAQs, see a few features I've got planned, submit feedback, and request an invite if you want to
                    join up.
                </p>
            </div>

            <section class="mt-12 space-y-8" aria-labelledby="faq-heading">
                <h1 id="faq-heading" class="text-3xl font-bold tracking-tight sm:text-4xl">FAQs</h1>

                <article class="border-t border-border pt-6">
                    <h2 class="text-lg font-semibold">What exactly <em>is</em> <BrandName />?</h2>
                    <div class="mt-2 space-y-3 text-muted-foreground">
                        <p>
                            <BrandName /> is a workout planning and logging app for people who are serious about their lifting. You can create
                            routines, log each set, and apply progressive overload to each exercise as you get stronger. I designed it to have the
                            features I felt were missing from other apps I tried.
                        </p>
                        <p>What's more important is what <BrandName /> is <em>not</em>. It is not:</p>
                        <ul class="list-disc space-y-4 pl-5">
                            <li>
                                <span class="font-medium text-foreground">A Personal Trainer.</span>
                                <p class="mt-1">
                                    I'm a Software Engineer, not a PT. I don't want to undercut the wonderful PTs at my gym, or to feign expertise
                                    where I have none. If you want an app to design your workouts and tell you how to do each exercise, this isn't it.
                                </p>
                            </li>
                            <li>
                                <span class="font-medium text-foreground">A training organiser.</span>
                                <p class="mt-1">
                                    <BrandName /> doesn't tell you when to do your workouts. It won't nag you to do Chest Day tomorrow or lose your
                                    streak. <BrandName /> helps you stick to your routine when you're at the gym — getting there is up to you!
                                </p>
                            </li>
                            <li>
                                <span class="font-medium text-foreground">A secret AI data mining project disguised as a workout app.</span>
                                <p class="mt-1">
                                    There is no AI tooling in <BrandName />, and there may never be. I have no interest in using your data to train a
                                    LLM or anything like that.
                                </p>
                            </li>
                        </ul>
                    </div>
                </article>

                <article class="border-t border-border pt-6">
                    <h2 class="text-lg font-semibold">What do you mean by 'Beta'?</h2>
                    <div class="mt-2 space-y-3 text-muted-foreground">
                        <p>
                            Beta means incomplete. It means that <BrandName /> is not really ready for mass consumption, and it may be missing
                            features you'd expect as standard in other apps.
                        </p>
                        <p>It also means that things might change often as I refine the experience and respond to suggestions and feedback.</p>
                    </div>
                </article>

                <article class="border-t border-border pt-6">
                    <h2 class="text-lg font-semibold">Do I have to pay to use this?</h2>
                    <div class="mt-2 space-y-3 text-muted-foreground">
                        <p>
                            No. And you never will. If I ever feel the need to charge a fee to users of this app, Beta testers will be exempt, for as
                            long as they have an account.
                        </p>
                    </div>
                </article>

                <article class="border-t border-border pt-6">
                    <h2 class="text-lg font-semibold">What do you get out of me being a Beta Tester?</h2>
                    <div class="mt-2 space-y-3 text-muted-foreground">
                        <p>As much as you're willing to give. You're welcome to simply use it and (hopefully) enjoy everything it has to offer.</p>
                        <p>
                            However, I'd really appreciate you providing feedback using the form linked at the bottom of this page, to let me know
                            what you think, what's good about <BrandName />, and what's missing.
                        </p>
                    </div>
                </article>

                <article class="border-t border-border pt-6">
                    <h2 class="text-lg font-semibold">This is cool! How can I support <BrandName />?</h2>
                    <div class="mt-2 space-y-3 text-muted-foreground">
                        <p>The best way you can support this app is to use it and provide feedback (more on that below).</p>
                        <p>
                            You can also tell your friends about it and recommend it, if you like it. More beta testers means a wider range of
                            feedback, which is always a good thing.
                        </p>
                        <p>
                            At the moment, it doesn't cost me much to run. It started as a personal project, and if it stays that way, I'm happy to
                            pay the small amount it costs me each month to maintain.
                        </p>
                        <p>
                            However, if you <em>do</em> want to support the app financially, I have a
                            <a
                                href="https://ko-fi.com/jamieb3108"
                                class="font-medium text-primary underline-offset-2 hover:underline"
                                rel="noopener noreferrer"
                                target="_blank"
                            >
                                Ko-Fi page
                            </a>
                            where you can donate to the upkeep of <BrandName />.
                        </p>
                    </div>
                </article>

                <article class="border-t border-border pt-6">
                    <h2 class="text-lg font-semibold">How do I learn the app?</h2>
                    <div class="mt-2 space-y-3 text-muted-foreground">
                        <p>
                            Read the
                            <Link :href="route('tutorial')" class="font-medium text-primary underline-offset-2 hover:underline">tutorial</Link>
                            — Preferences, Account, routines, Play, and what happens after you finish. Launched 15 August 2026.
                        </p>
                    </div>
                </article>

                <article class="border-t border-border pt-6">
                    <h2 class="text-lg font-semibold">How do I install the app on my phone?</h2>
                    <div class="mt-2 space-y-3 text-muted-foreground">
                        <p>
                            <BrandName /> is a progressive web app — open it in your phone browser, then add it to your home screen for a full-screen
                            icon (no App Store / Play Store).
                        </p>
                        <ul class="list-disc space-y-4 pl-5">
                            <li>
                                <span class="font-medium text-foreground">iPhone / iPad (Safari)</span>
                                <p class="mt-1">
                                    Tap Share, then <span class="font-medium text-foreground">Add to Home Screen</span>. If you dismissed the in-app
                                    tip, those steps still work anytime.
                                </p>
                            </li>
                            <li>
                                <span class="font-medium text-foreground">Android (Chrome)</span>
                                <p class="mt-1">
                                    Open the browser menu (⋮), then choose <span class="font-medium text-foreground">Install app</span> or
                                    <span class="font-medium text-foreground">Add to Home screen</span>.
                                </p>
                            </li>
                        </ul>
                        <p>
                            More detail is in the
                            <Link :href="`${route('tutorial')}#install`" class="font-medium text-primary underline-offset-2 hover:underline">
                                tutorial
                            </Link>
                            .
                        </p>
                    </div>
                </article>

                <article class="border-t border-border pt-6">
                    <h2 class="text-lg font-semibold">Is my data safe?</h2>
                    <div class="mt-2 space-y-3 text-muted-foreground">
                        <p>
                            See the
                            <Link :href="route('privacy')" class="font-medium text-primary underline-offset-2 hover:underline">privacy policy</Link>.
                        </p>
                    </div>
                </article>
            </section>

            <section class="cta-panel mt-12 rounded-md border-2 border-primary bg-primary/15 px-5 py-7 sm:px-7" aria-labelledby="interest-heading">
                <h2 id="interest-heading" class="text-2xl font-bold tracking-tight text-primary">I want to try it!</h2>
                <p class="mt-2 text-sm text-foreground/90 sm:text-base">
                    If you're keen to give <BrandName /> a try, click the button below, fill in the form, and I'll send you an invitation to your
                    email address.
                </p>
                <p class="mt-5">
                    <Link
                        :href="route('invite-request')"
                        class="inline-flex rounded-md bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground transition-opacity hover:opacity-90"
                    >
                        Request an invite
                    </Link>
                </p>
            </section>

            <section class="mt-14 border-t border-border pt-10" aria-labelledby="backlog-heading">
                <h2 id="backlog-heading" class="text-2xl font-bold tracking-tight">Current backlog</h2>
                <p class="mt-3 text-muted-foreground">
                    This shows features currently on the backlog. The list isn't exhaustive, and some items — especially bigger ones near the bottom —
                    may take a while or never ship. Before you request or suggest a feature, please check below. If it's already here and it matters
                    to you, tell me — that makes it more likely I'll prioritise it.
                </p>

                <ol class="mt-6 list-decimal space-y-5 pl-5 text-muted-foreground">
                    <li v-for="item in backlogItems" :key="item.title" class="pl-1">
                        <p class="font-medium text-foreground">
                            <span :class="recentlyAdded(item) ? 'line-through decoration-muted-foreground/80' : undefined">{{ item.title }}</span>
                            <span
                                v-if="recentlyAdded(item)"
                                class="ml-2 inline-block align-middle font-mono text-xs font-medium tracking-wide text-primary uppercase no-underline"
                            >
                                Recently added
                            </span>
                        </p>
                        <p class="mt-1" :class="recentlyAdded(item) ? 'line-through decoration-muted-foreground/60' : undefined">
                            <BrandCopy :text="item.description" />
                        </p>
                    </li>
                </ol>
            </section>

            <section class="cta-panel mt-12 rounded-md border-2 border-primary bg-primary/15 px-5 py-7 sm:px-7" aria-labelledby="feedback-heading">
                <h2 id="feedback-heading" class="text-2xl font-bold tracking-tight text-primary">Feedback</h2>
                <p class="mt-2 text-sm text-foreground/90 sm:text-base">
                    Using <BrandName /> does not come with any obligation to provide feedback. If you want to share thoughts, what's working, or
                    what's missing, use the form below.
                </p>
                <p class="mt-5">
                    <Link
                        :href="route('feedback')"
                        class="inline-flex rounded-md bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground transition-opacity hover:opacity-90"
                    >
                        Submit feedback
                    </Link>
                </p>
            </section>
        </main>
    </div>
</template>

<style scoped>
.faq-atmosphere {
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
