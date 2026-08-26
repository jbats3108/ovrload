<script setup lang="ts">
import BrandName from '@/components/BrandName.vue';
import PublicSiteHeader from '@/components/PublicSiteHeader.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Privacy policy" />

    <div class="privacy relative min-h-dvh bg-background text-foreground">
        <div class="privacy-atmosphere pointer-events-none absolute inset-0" aria-hidden="true" />

        <PublicSiteHeader current="privacy" />

        <main class="relative z-10 mx-auto w-full max-w-2xl px-6 pb-20 sm:px-10">
            <p class="text-sm font-medium tracking-widest text-primary uppercase">Legal</p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Privacy policy</h1>
            <p class="mt-2 text-sm text-muted-foreground">Last updated: 4 August 2026</p>

            <div class="mt-10 space-y-10 text-muted-foreground">
                <section class="space-y-3" aria-labelledby="who-heading">
                    <h2 id="who-heading" class="text-lg font-semibold text-foreground">1. Who we are</h2>
                    <p><BrandName /> is an invite-only workout planning and logging app.</p>
                    <p>
                        The data controller is <span class="text-foreground">Jamie Batabyal</span>. Questions or privacy requests:
                        <a class="text-primary underline-offset-2 hover:underline" href="mailto:admin@ovr-load.co.uk">admin@ovr-load.co.uk</a>.
                    </p>
                </section>

                <section class="space-y-3" aria-labelledby="service-heading">
                    <h2 id="service-heading" class="text-lg font-semibold text-foreground">2. What this service is</h2>
                    <p>
                        <BrandName /> lets invited beta users build routines, log sets, and track progressive overload. It is not a coaching or
                        medical service. Registration is invite-only; there is no public signup.
                    </p>
                </section>

                <section class="space-y-3" aria-labelledby="collect-heading">
                    <h2 id="collect-heading" class="text-lg font-semibold text-foreground">3. What we collect</h2>
                    <p class="font-medium text-foreground">Account</p>
                    <ul class="list-disc space-y-1 pl-5">
                        <li>Name</li>
                        <li>Email address</li>
                        <li>Password (stored hashed; we cannot read it)</li>
                        <li>Email verification timestamp if used</li>
                        <li>Role (e.g. admin vs regular user)</li>
                    </ul>
                    <p class="font-medium text-foreground">App data you create</p>
                    <ul class="list-disc space-y-1 pl-5">
                        <li>Routines and their structure (blocks, exercises, prescribed reps/weights, warm-ups, dropsets, supersets)</li>
                        <li>Workout logs (sets, weights, reps, notes, status, timestamps)</li>
                        <li>Training preferences (e.g. weight unit, progression/deload defaults, warm-up defaults)</li>
                        <li>Plate profiles (bar and plate inventory for your gym setup)</li>
                        <li>Custom exercises you add</li>
                    </ul>
                    <p class="font-medium text-foreground">Invites (admin-created)</p>
                    <ul class="list-disc space-y-1 pl-5">
                        <li>Invitee email, token, expiry, who created/used the invite, optional note</li>
                    </ul>
                    <p class="font-medium text-foreground">Public forms (invite request &amp; feedback)</p>
                    <ul class="list-disc space-y-1 pl-5">
                        <li>Name, email address, and message you submit</li>
                        <li>Form type (invite interest or feedback)</li>
                        <li>For feedback: category (bug / feature / general)</li>
                        <li>Technical metadata: IP address and browser user agent at submit time</li>
                    </ul>
                    <p class="font-medium text-foreground">Technical</p>
                    <ul class="list-disc space-y-1 pl-5">
                        <li>Session records: IP address and browser user agent (while you are logged in)</li>
                        <li>Essential cookies (see Cookies)</li>
                        <li>Browser notification permission if you enable rest-end alerts (permission lives in your browser)</li>
                    </ul>
                    <p>
                        We do <strong class="text-foreground">not</strong> collect date of birth, address, phone number, payment card details, GPS, or
                        social-login tokens in this beta.
                    </p>
                </section>

                <section class="space-y-3" aria-labelledby="why-heading">
                    <h2 id="why-heading" class="text-lg font-semibold text-foreground">4. Why we use it (and legal bases)</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[28rem] border-collapse text-left text-sm">
                            <thead>
                                <tr class="border-b border-border text-foreground">
                                    <th class="py-2 pr-3 font-medium">Purpose</th>
                                    <th class="py-2 pr-3 font-medium">Examples</th>
                                    <th class="py-2 font-medium">Legal basis</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-b border-border/60 align-top">
                                    <td class="py-2 pr-3">Provide the app</td>
                                    <td class="py-2 pr-3">Account, routines, workouts, prefs</td>
                                    <td class="py-2">Contract — running the beta service you signed up for</td>
                                </tr>
                                <tr class="border-b border-border/60 align-top">
                                    <td class="py-2 pr-3">Security and abuse prevention</td>
                                    <td class="py-2 pr-3">Sessions, password resets, invite tokens</td>
                                    <td class="py-2">Legitimate interests — keep the service secure</td>
                                </tr>
                                <tr class="border-b border-border/60 align-top">
                                    <td class="py-2 pr-3">Service email</td>
                                    <td class="py-2 pr-3">Invites, password reset (and verification if enabled)</td>
                                    <td class="py-2">Contract / legitimate interests — necessary to operate the account</td>
                                </tr>
                                <tr class="border-b border-border/60 align-top">
                                    <td class="py-2 pr-3">Beta forms</td>
                                    <td class="py-2 pr-3">Invite requests and feedback submissions</td>
                                    <td class="py-2">Legitimate interests — run the beta and respond to you</td>
                                </tr>
                                <tr class="align-top">
                                    <td class="py-2 pr-3">Fix bugs</td>
                                    <td class="py-2 pr-3">Error reports via Sentry (configured not to send default PII)</td>
                                    <td class="py-2">Legitimate interests — keep the app working</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p>
                        We do <strong class="text-foreground">not</strong> sell your data. We do <strong class="text-foreground">not</strong> share it
                        with advertisers. We do <strong class="text-foreground">not</strong> use your data to train AI or large language models.
                    </p>
                </section>

                <section class="space-y-3" aria-labelledby="share-heading">
                    <h2 id="share-heading" class="text-lg font-semibold text-foreground">5. Who we share with</h2>
                    <p>
                        We use a small number of service providers (<strong class="text-foreground">processors</strong>) who handle data only to run
                        <BrandName />:
                    </p>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[28rem] border-collapse text-left text-sm">
                            <thead>
                                <tr class="border-b border-border text-foreground">
                                    <th class="py-2 pr-3 font-medium">Provider</th>
                                    <th class="py-2 pr-3 font-medium">Role</th>
                                    <th class="py-2 font-medium">Typical data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-b border-border/60 align-top">
                                    <td class="py-2 pr-3 text-foreground">Laravel Cloud</td>
                                    <td class="py-2 pr-3">Hosts the application and database</td>
                                    <td class="py-2">Account, app data, and public form submissions you store in <BrandName /></td>
                                </tr>
                                <tr class="border-b border-border/60 align-top">
                                    <td class="py-2 pr-3 text-foreground">Resend</td>
                                    <td class="py-2 pr-3">Sends transactional email; may receive mail to domain addresses</td>
                                    <td class="py-2">Email address; invite/reset/form notification content</td>
                                </tr>
                                <tr class="border-b border-border/60 align-top">
                                    <td class="py-2 pr-3 text-foreground">Sentry</td>
                                    <td class="py-2 pr-3">Application error monitoring</td>
                                    <td class="py-2">Technical error context; we keep default PII sending off</td>
                                </tr>
                                <tr class="align-top">
                                    <td class="py-2 pr-3 text-foreground">Bunny Fonts</td>
                                    <td class="py-2 pr-3">Serves web fonts to your browser</td>
                                    <td class="py-2">Connection metadata (e.g. IP) as with any CDN request</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p>
                        If we ever add another processor (e.g. analytics or Strava), we will update this page first. Admins of
                        <BrandName /> can see user names/emails and invite details needed to run the beta. They do not browse other users’ workout
                        contents through a dedicated admin tool today.
                    </p>
                </section>

                <section class="space-y-3" aria-labelledby="cookies-heading">
                    <h2 id="cookies-heading" class="text-lg font-semibold text-foreground">6. Cookies</h2>
                    <p>
                        We only use cookies needed to run the site. We do <strong class="text-foreground">not</strong> use advertising or analytics
                        cookies.
                    </p>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[20rem] border-collapse text-left text-sm">
                            <thead>
                                <tr class="border-b border-border text-foreground">
                                    <th class="py-2 pr-3 font-medium">Cookie / storage</th>
                                    <th class="py-2 font-medium">Purpose</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-b border-border/60 align-top">
                                    <td class="py-2 pr-3 font-mono text-xs text-foreground">ovrload_session</td>
                                    <td class="py-2">Keep you logged in / maintain your session</td>
                                </tr>
                                <tr class="border-b border-border/60 align-top">
                                    <td class="py-2 pr-3 text-foreground">Laravel CSRF / XSRF cookie</td>
                                    <td class="py-2">Protect forms from cross-site request forgery</td>
                                </tr>
                                <tr class="border-b border-border/60 align-top">
                                    <td class="py-2 pr-3 font-mono text-xs text-foreground">appearance</td>
                                    <td class="py-2">Remember light/dark (or system) preference</td>
                                </tr>
                                <tr class="align-top">
                                    <td class="py-2 pr-3 font-mono text-xs text-foreground">sidebar_state</td>
                                    <td class="py-2">Remember UI sidebar open/closed</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p>
                        Some preferences may also be stored in your browser’s <span class="font-mono text-xs text-foreground">localStorage</span>
                        (appearance, PWA install dismiss). That stays on your device.
                    </p>
                </section>

                <section class="space-y-3" aria-labelledby="retention-heading">
                    <h2 id="retention-heading" class="text-lg font-semibold text-foreground">7. How long we keep it</h2>
                    <ul class="list-disc space-y-2 pl-5">
                        <li><span class="text-foreground">Account and app data:</span> for as long as your account exists.</li>
                        <li>
                            <span class="text-foreground">Public form submissions:</span> until we have handled them and no longer need them for the
                            beta (or sooner if you ask us to erase a submission). They are not removed by deleting an app account, because many
                            submitters do not have an account.
                        </li>
                        <li><span class="text-foreground">Sessions:</span> up to about 30 days of idle lifetime (then they expire).</li>
                        <li>
                            <span class="text-foreground">When you delete your account:</span> we permanently remove your account and associated app
                            data from the live database (routines, workouts, customs, plate profile, related session/reset/invite rows as
                            implemented).
                        </li>
                        <li>
                            <span class="text-foreground">Backups and processors:</span> copies may remain for a short time in host backups or in
                            email/error systems under those providers’ retention rules. We cannot instantly wipe every Resend or Sentry record from
                            their systems via the delete button.
                        </li>
                    </ul>
                </section>

                <section class="space-y-3" aria-labelledby="rights-heading">
                    <h2 id="rights-heading" class="text-lg font-semibold text-foreground">8. Your rights</h2>
                    <p>Depending on where you live (UK / EEA and similar), you can:</p>
                    <ul class="list-disc space-y-2 pl-5">
                        <li>
                            <span class="text-foreground">Access / export</span> — download a copy of your data from Account, or email
                            <a class="text-primary underline-offset-2 hover:underline" href="mailto:admin@ovr-load.co.uk">admin@ovr-load.co.uk</a>
                        </li>
                        <li><span class="text-foreground">Rectify</span> — update name/email in Account and training preferences in Preferences</li>
                        <li>
                            <span class="text-foreground">Erase</span> — delete your account in Account (password required); for a form submission
                            without an account, email us
                        </li>
                        <li><span class="text-foreground">Restrict or object</span> — email us and we will consider the request for this beta</li>
                        <li>
                            <span class="text-foreground">Complain</span> — UK:
                            <a
                                class="text-primary underline-offset-2 hover:underline"
                                href="https://ico.org.uk"
                                rel="noopener noreferrer"
                                target="_blank"
                                >Information Commissioner’s Office (ICO)</a
                            >. If you are in the EU, you can contact your local supervisory authority
                        </li>
                    </ul>
                    <p>We will respond to privacy emails as soon as we reasonably can. For a small beta that is usually days, not months.</p>
                </section>

                <section class="space-y-3" aria-labelledby="transfers-heading">
                    <h2 id="transfers-heading" class="text-lg font-semibold text-foreground">9. International transfers</h2>
                    <p>
                        The app and some processors may process data in the <strong class="text-foreground">United States</strong> or other countries
                        outside the UK/EEA (for example hosting, email, or error monitoring). Where required, we rely on those providers’ data
                        processing terms, which typically include Standard Contractual Clauses and/or frameworks such as the EU–US Data Privacy
                        Framework.
                    </p>
                </section>

                <section class="space-y-3" aria-labelledby="changes-heading">
                    <h2 id="changes-heading" class="text-lg font-semibold text-foreground">10. Changes</h2>
                    <p>
                        We may update this policy as the beta evolves. The <strong class="text-foreground">Last updated</strong> date at the top will
                        change. Material changes will be called out to beta users when practical (e.g. email or in-app note).
                    </p>
                    <p>
                        If something here is unclear, email
                        <a class="text-primary underline-offset-2 hover:underline" href="mailto:admin@ovr-load.co.uk">admin@ovr-load.co.uk</a>
                        — short questions welcome.
                    </p>
                </section>
            </div>
        </main>
    </div>
</template>

<style scoped>
.privacy-atmosphere {
    background:
        radial-gradient(ellipse 70% 40% at 50% -5%, color-mix(in oklab, var(--primary) 18%, transparent), transparent 70%),
        radial-gradient(ellipse 40% 30% at 90% 60%, color-mix(in oklab, var(--accent) 10%, transparent), transparent 65%);
}
</style>
