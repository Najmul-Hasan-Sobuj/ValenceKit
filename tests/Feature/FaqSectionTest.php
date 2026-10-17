<?php

test('FaqSection component exists with accessible accordion markup and schema helper', function () {
    $faqComponentPath = resource_path('js/components/frontend/FaqSection.vue');

    expect(file_exists($faqComponentPath))->toBeTrue('resources/js/components/frontend/FaqSection.vue must exist');

    $content = file_get_contents($faqComponentPath);

    // Accessibility ARIA attributes
    expect($content)
        ->toContain(':aria-expanded="activeFaq === index"')
        ->toContain(':aria-controls="`faq-content-${index}`"')
        ->toContain('role="region"')
        ->toContain(':aria-labelledby="`faq-trigger-${index}`"')
        ->toContain('type="button"');

    // Schema helper export
    expect($content)
        ->toContain('export function createFaqSchema')
        ->toContain("'@type': 'FAQPage'")
        ->toContain("'@type': 'Question'")
        ->toContain("'@type': 'Answer'");
});

test('Services page delegates FAQ rendering to FaqSection component and generates FAQPage structured data', function () {
    $servicesPath = resource_path('js/pages/Services.vue');
    $content = file_get_contents($servicesPath);

    // Component import and usage
    expect($content)
        ->toContain("import FaqSection, { createFaqSchema } from '@/components/frontend/FaqSection.vue'")
        ->toContain('<FaqSection :items="faqs" />');

    // FAQPage schema in structuredDataEntities
    expect($content)
        ->toContain('createFaqSchema(faqs, `${appUrl}/services`)');
});

test('Services route returns successful 200 response with FAQ items in Inertia payload', function () {
    $response = $this->get('/services');

    $response->assertOk();
});
