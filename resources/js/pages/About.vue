<script setup lang="ts">
import BrandName from '@/components/BrandName.vue';
import PublicSiteHeader from '@/components/PublicSiteHeader.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="About" />

    <div class="about relative min-h-dvh bg-background text-foreground">
        <div class="about-atmosphere pointer-events-none absolute inset-0" aria-hidden="true" />

        <PublicSiteHeader current="about" />

        <main class="relative z-10 mx-auto w-full max-w-2xl px-6 pb-20 sm:px-10">
            <p class="text-sm font-medium tracking-widest text-primary uppercase">About</p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                Why
                <BrandName />
                exists
            </h1>
            <p class="mt-3 text-muted-foreground">A short story from the person who built it — and what it’s meant to help with at the gym.</p>

            <div class="mt-12 space-y-12 text-muted-foreground">
                <section class="space-y-3" aria-labelledby="about-me-heading">
                    <h2 id="about-me-heading" class="text-lg font-semibold text-foreground">About me</h2>
                    <p>
                        I’m Jamie. I’ve been lifting with purpose for the past three years — getting stronger, lifting heavier, and keeping my head on
                        straight. I recently joined the 1,000 lb club.
                    </p>
                    <p>
                        In that time I’ve tried pen and paper, spreadsheets, and a pile of fitness apps. I have ADHD: I forget easily, and my
                        executive function is already spoken for at the gym.
                    </p>
                    <p>
                        Other tools expected me to do the heavy cognitive lifting — update the plan, bump the weights, recall what’s next — as well as
                        the actual heavy lifting. I needed a tool that does it for me instead.
                    </p>
                    <p>
                        So I put my day job as a software engineer to work and built the tool I actually wanted — everything I craved in one place.
                        That tool is
                        <BrandName />
                        .
                    </p>
                </section>

                <section class="space-y-3" aria-labelledby="why-heading">
                    <h2 id="why-heading" class="text-lg font-semibold text-foreground">Why I built it</h2>
                    <p>
                        I wanted planning, logging, and progressive overload in the same flow — stick to your plan, log cleanly on the floor, and
                        carry last session into the next, without a spreadsheet waiting at home.
                    </p>
                    <p>It’s still beta, still changing, and still a personal project I run for people who want to try it with me.</p>
                </section>

                <section class="space-y-3" aria-labelledby="wants-heading">
                    <h2 id="wants-heading" class="text-lg font-semibold text-foreground">Who is it for?</h2>
                    <p>People who want:</p>
                    <ul class="list-disc space-y-4 pl-5">
                        <li>
                            <span class="font-medium text-foreground">A clear plan on the floor</span>
                            <p class="mt-1">
                                Build routines with blocks, supersets, circuits, warm-ups, and dropsets — then walk through them set by set when
                                you’re at the gym.
                            </p>
                        </li>
                        <li>
                            <span class="font-medium text-foreground">Overload built into the flow</span>
                            <p class="mt-1">
                                Hit your targets, then bump working weights as you get stronger, without rebuilding the whole routine from scratch.
                            </p>
                        </li>
                        <li>
                            <span class="font-medium text-foreground">A gym companion, not a coach</span>
                            <p class="mt-1">
                                Help sticking to
                                <em>your</em>
                                routine while you’re there — without streaks, nagging, or an AI rewriting your training.
                            </p>
                        </li>
                    </ul>
                    <p>
                        I’m not a personal trainer, so
                        <BrandName />
                        won’t plan your program or teach form — it helps you run the plan you bring.
                    </p>
                </section>
            </div>

            <p class="mt-14 border-t border-border pt-8 text-muted-foreground">
                Curious about beta, invites, or what’s on the backlog?
                <Link :href="route('beta-tester-faqs')" class="font-medium text-primary underline-offset-2 hover:underline">
                    Read the Beta FAQs
                </Link>
                .
            </p>
        </main>
    </div>
</template>

<style scoped>
.about-atmosphere {
    background:
        radial-gradient(ellipse 70% 40% at 50% -5%, color-mix(in oklab, var(--primary) 18%, transparent), transparent 70%),
        radial-gradient(ellipse 40% 30% at 90% 60%, color-mix(in oklab, var(--accent) 10%, transparent), transparent 65%);
}
</style>
