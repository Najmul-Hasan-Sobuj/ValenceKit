<script setup lang="ts">
import { ref } from 'vue';
import { Plus, X } from '@lucide/vue';

export interface FaqItem {
    letter?: string;
    question: string;
    answer: string;
}

const props = withDefaults(
    defineProps<{
        items: FaqItem[];
        eyebrow?: string;
        title?: string;
        description?: string;
        initialOpenIndex?: number | null;
        id?: string;
    }>(),
    {
        eyebrow: 'FAQ',
        title: 'Frequently Asked Questions',
        description:
            'Practical answers regarding technical requirements, server setups, and project delivery.',
        initialOpenIndex: 0,
        id: 'faq',
    },
);

const activeFaq = ref<number | null>(props.initialOpenIndex);

const toggleFaq = (index: number) => {
    activeFaq.value = activeFaq.value === index ? null : index;
};
</script>

<script lang="ts">
export function createFaqSchema(faqs: FaqItem[], url?: string) {
    return {
        '@type': 'FAQPage',
        ...(url ? { '@id': `${url}#faq` } : {}),
        mainEntity: faqs.map((faq) => ({
            '@type': 'Question',
            name: faq.question,
            acceptedAnswer: {
                '@type': 'Answer',
                text: faq.answer,
            },
        })),
    };
}
</script>

<template>
    <section
        :id="id"
        class="w-full space-y-12 border-t border-border pt-16 lg:pt-24"
    >
        <!-- Header -->
        <div class="max-w-3xl space-y-4">
            <p
                v-if="eyebrow"
                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
            >
                {{ eyebrow }}
            </p>
            <h2
                class="text-3xl font-medium tracking-tight text-foreground md:text-5xl"
            >
                {{ title }}
            </h2>
            <p
                v-if="description"
                class="text-xl leading-relaxed font-normal text-muted-foreground lg:text-2xl"
            >
                {{ description }}
            </p>
        </div>

        <!-- Full-Width Modern Accordion List -->
        <div class="w-full divide-y divide-border border-t border-border">
            <div
                v-for="(faq, index) in items"
                :key="index"
                class="group/faq py-2 transition-colors"
            >
                <button
                    :id="`faq-trigger-${index}`"
                    type="button"
                    @click="toggleFaq(index)"
                    class="flex w-full cursor-pointer items-start justify-between gap-4 py-6 text-left focus:outline-none sm:items-center sm:gap-6 sm:py-8"
                    :aria-expanded="activeFaq === index"
                    :aria-controls="`faq-content-${index}`"
                >
                    <div class="flex items-start gap-4 sm:items-center sm:gap-6">
                        <!-- Alphabet Badge matching Phase design -->
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-colors duration-300 sm:size-10 sm:text-sm"
                            :class="
                                activeFaq === index
                                    ? 'scale-105 bg-foreground text-background'
                                    : 'bg-muted text-foreground group-hover/faq:bg-foreground group-hover/faq:text-background'
                            "
                        >
                            {{ faq.letter || String.fromCharCode(65 + index) }}
                        </span>
                        <h3
                            class="text-xl font-normal tracking-tight text-foreground transition-all duration-300 sm:text-2xl"
                        >
                            <span class="collapsible-title-text inline-block">
                                {{ faq.question }}
                            </span>
                        </h3>
                    </div>

                    <!-- Toggle Plus / X icon badge -->
                    <div
                        class="flex size-8 shrink-0 items-center justify-center rounded-md border border-border bg-card transition-colors duration-300"
                    >
                        <X
                            v-if="activeFaq === index"
                            class="size-4 text-foreground transition-transform duration-300"
                            aria-hidden="true"
                        />
                        <Plus
                            v-else
                            class="size-4 text-muted-foreground transition-transform duration-300 group-hover/faq:rotate-90 group-hover/faq:text-foreground"
                            aria-hidden="true"
                        />
                    </div>
                </button>

                <!-- Expanded Answer with generous indentation -->
                <div
                    v-if="activeFaq === index"
                    :id="`faq-content-${index}`"
                    role="region"
                    :aria-labelledby="`faq-trigger-${index}`"
                    class="max-w-4xl animate-in pr-4 pb-8 pl-13 text-base leading-relaxed text-muted-foreground duration-200 fade-in slide-in-from-top-2 sm:pr-14 sm:pl-16 sm:text-lg"
                >
                    <p>{{ faq.answer }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
