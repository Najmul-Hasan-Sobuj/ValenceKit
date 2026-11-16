<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { saveTrackingConsent, type TrackingState } from '@/lib/tracking';
import { privacyPolicy } from '@/routes';

const page = usePage<{ tracking: TrackingState }>();
const preferences = ref(false);
const analytics = ref(page.props.tracking.consent?.analytics ?? false);
const marketing = ref(page.props.tracking.consent?.marketing ?? false);
const error = ref('');
const saving = ref(false);
function save(allowAnalytics: boolean, allowMarketing: boolean) {
    if (saving.value) return;
    error.value = '';
    try {
        saveTrackingConsent({
            version: 1,
            analytics: allowAnalytics,
            marketing: allowMarketing,
        });
        saving.value = true;
        window.location.reload();
    } catch (reason) {
        error.value =
            reason instanceof Error
                ? reason.message
                : 'Could not save preferences.';
    }
}
</script>

<template>
    <div v-if="page.props.tracking.available && page.props.tracking.allowed">
        <button
            type="button"
            class="text-sm underline underline-offset-4 hover:text-foreground"
            @click="preferences = true"
        >
            Cookie preferences
        </button>
        <section
            v-if="!page.props.tracking.consent"
            aria-labelledby="cookie-title"
            class="fixed inset-x-3 bottom-3 z-50 mx-auto max-h-[calc(100dvh-1.5rem)] max-w-2xl overflow-y-auto rounded-xl border bg-background p-4 text-foreground shadow-xl sm:p-5"
        >
            <h2 id="cookie-title" class="text-base font-semibold">
                Your privacy choices
            </h2>
            <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                Essential cookies keep this site working. With your permission,
                analytics helps us improve the site and marketing measures
                campaigns. Optional tracking stays off until you choose.
                <Link :href="privacyPolicy()" class="underline"
                    >Privacy policy</Link
                >
            </p>
            <p v-if="error" role="alert" class="mt-2 text-sm text-destructive">
                {{ error }}
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    :disabled="saving"
                    @click="save(false, false)"
                    >Reject optional</Button
                >
                <Button
                    variant="outline"
                    :disabled="saving"
                    @click="preferences = true"
                    >Choose preferences</Button
                >
                <Button
                    variant="outline"
                    :disabled="saving"
                    @click="save(true, true)"
                    >Accept all</Button
                >
            </div>
        </section>
        <Dialog v-model:open="preferences">
            <DialogContent
                class="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-md"
            >
                <DialogHeader
                    ><DialogTitle>Cookie preferences</DialogTitle
                    ><DialogDescription
                        >Essential cookies are always on. You can change
                        optional categories at any time; saving reloads the
                        page.</DialogDescription
                    ></DialogHeader
                >
                <div class="flex items-start justify-between gap-4 py-2">
                    <div>
                        <Label for="consent-analytics">Analytics</Label>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Usage measurement and session replay (GA4 and
                            Clarity).
                        </p>
                    </div>
                    <Switch
                        id="consent-analytics"
                        v-model="analytics"
                        aria-label="Allow analytics cookies"
                        :disabled="saving"
                    />
                </div>
                <div class="flex items-start justify-between gap-4 py-2">
                    <div>
                        <Label for="consent-marketing">Marketing</Label>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Advertising measurement, including Meta Pixel.
                        </p>
                    </div>
                    <Switch
                        id="consent-marketing"
                        v-model="marketing"
                        aria-label="Allow marketing cookies"
                        :disabled="saving"
                    />
                </div>
                <p class="text-xs text-muted-foreground">
                    Tag Manager and custom scripts need both categories because
                    they may contain mixed tracking. Rejecting optional cookies
                    does not affect contact forms.
                </p>
                <p v-if="error" role="alert" class="text-sm text-destructive">
                    {{ error }}
                </p>
                <DialogFooter
                    ><Button
                        :disabled="saving"
                        @click="save(analytics, marketing)"
                        >Save preferences</Button
                    ></DialogFooter
                >
            </DialogContent>
        </Dialog>
    </div>
</template>
