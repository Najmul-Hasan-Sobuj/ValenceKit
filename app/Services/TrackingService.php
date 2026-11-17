<?php

namespace App\Services;

use App\Models\TrackingSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Vite;

class TrackingService
{
    /** @return array{version: int, analytics: bool, marketing: bool}|null */
    public static function consent(): ?array
    {
        $cookie = Request::cookie('tracking_consent');
        $consent = is_string($cookie) ? json_decode($cookie, true) : null;

        if (! is_array($consent) || ($consent['version'] ?? null) !== 1 || ! is_bool($consent['analytics'] ?? null) || ! is_bool($consent['marketing'] ?? null)) {
            return null;
        }

        return ['version' => 1, 'analytics' => $consent['analytics'], 'marketing' => $consent['marketing']];
    }

    public static function consentAllows(string $category): bool
    {
        $consent = static::consent();

        if ($consent === null) {
            return false;
        }

        return match ($category) {
            'all' => $consent['analytics'] && $consent['marketing'],
            'analytics' => $consent['analytics'], 'marketing' => $consent['marketing'], default => false,
        };
    }

    /** @return array<string, mixed> */
    public static function publicState(): array
    {
        $settings = TrackingSetting::instance();
        $providers = [
            'clarity' => $settings->clarity_enabled && ! empty($settings->clarity_project_id),
            'ga4' => $settings->google_analytics_enabled && ! empty($settings->google_analytics_id),
            'gtm' => $settings->google_tag_manager_enabled && ! empty($settings->google_tag_manager_id),
            'meta' => $settings->facebook_pixel_enabled && ! empty($settings->facebook_pixel_id),
            'custom' => $settings->custom_scripts_enabled && (! empty($settings->custom_head_scripts) || ! empty($settings->custom_body_scripts)),
        ];

        return ['available' => in_array(true, $providers, true), 'allowed' => static::shouldTrack(), 'consent' => static::consent(), 'providers' => $providers];
    }

    /**
     * Determine if tracking scripts should be rendered for the current request.
     */
    public static function shouldTrack(): bool
    {
        $publicPaths = array_map(static fn (array $page): string => trim($page['path'], '/') ?: '/', app(SeoService::class)->getPublicPages());
        if (! in_array(Request::path(), $publicPaths, true)) {
            return false;
        }
        $settings = TrackingSetting::instance();

        if ($settings->exclude_admins) {
            $adminPrefix = trim((string) config('admin.alias', 'admin'), '/');

            // Check if current request path is within admin area
            if (Request::is($adminPrefix, "{$adminPrefix}/*")) {
                return false;
            }

            // Check if current user is logged in as an admin
            if (Auth::guard('admin')->check()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Render all active tracking scripts and custom tags intended for the HTML <head>.
     */
    public static function renderHeadScripts(): string
    {
        $adminPrefix = trim((string) config('admin.alias', 'admin'), '/');

        if (! static::shouldTrack()) {
            if (Auth::guard('admin')->check() && ! Request::is($adminPrefix, "{$adminPrefix}/*")) {
                return "<!-- Tracking scripts suppressed: Logged in as administrator (to view, open in an incognito window or disable 'Exclude Authenticated Admins' in Admin Settings > Tracking & Pixels) -->";
            }

            return '';
        }

        $settings = TrackingSetting::instance();
        $nonce = Vite::cspNonce() ?? '';
        $html = [];

        $marketingConsent = static::consentAllows('marketing') ? 'granted' : 'denied';
        if (static::publicState()['available']) {
            $analyticsConsent = static::consentAllows('analytics') ? 'granted' : 'denied';
            $html[] = <<<HTML
<script nonce="{$nonce}">
window.dataLayer = window.dataLayer || [];
window.gtag = window.gtag || function(){window.dataLayer.push(arguments);};
gtag('consent', 'default', {analytics_storage: '{$analyticsConsent}', ad_storage: '{$marketingConsent}', ad_user_data: '{$marketingConsent}', ad_personalization: '{$marketingConsent}'});
</script>
HTML;
        }

        // 1. Microsoft Clarity
        if ($settings->clarity_enabled && ! empty($settings->clarity_project_id) && static::consentAllows('analytics')) {
            $clarityId = htmlspecialchars($settings->clarity_project_id, ENT_QUOTES, 'UTF-8');
            $html[] = <<<HTML
<!-- Microsoft Clarity -->
<script type="text/javascript" nonce="{$nonce}">
    (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window, document, "clarity", "script", "{$clarityId}");
    clarity('consentv2', {analytics_Storage: 'granted', ad_Storage: '{$marketingConsent}'});
</script>
HTML;
        }

        // 2. Google Tag Manager (Head)
        if ($settings->google_tag_manager_enabled && ! empty($settings->google_tag_manager_id) && static::consentAllows('all')) {
            $gtmId = htmlspecialchars($settings->google_tag_manager_id, ENT_QUOTES, 'UTF-8');
            $html[] = <<<HTML
<!-- Google Tag Manager -->
<script nonce="{$nonce}">(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.setAttribute('nonce','{$nonce}');j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{$gtmId}');</script>
HTML;
        }

        // 3. Google Analytics 4 (gtag.js)
        if ($settings->google_analytics_enabled && ! empty($settings->google_analytics_id) && static::consentAllows('analytics')) {
            $gaId = htmlspecialchars($settings->google_analytics_id, ENT_QUOTES, 'UTF-8');
            $html[] = <<<HTML
<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$gaId}" nonce="{$nonce}"></script>
<script nonce="{$nonce}">
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '{$gaId}');
</script>
HTML;
        }

        // 4. Meta (Facebook) Pixel
        if ($settings->facebook_pixel_enabled && ! empty($settings->facebook_pixel_id) && static::consentAllows('marketing')) {
            $pixelId = htmlspecialchars($settings->facebook_pixel_id, ENT_QUOTES, 'UTF-8');
            $html[] = <<<HTML
<!-- Meta Pixel Code -->
<script nonce="{$nonce}">
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '{$pixelId}');
fbq('track', 'PageView');
</script>
HTML;
        }

        // 5. Custom Head Scripts
        if ($settings->custom_scripts_enabled && ! empty($settings->custom_head_scripts) && static::consentAllows('all')) {
            $customScripts = static::injectNonceIntoScripts($settings->custom_head_scripts, $nonce);
            $html[] = "<!-- Custom Head Scripts -->\n{$customScripts}";
        }

        return implode("\n", $html);
    }

    /**
     * Render all active tracking tags intended for immediately after the <body> opening tag.
     */
    public static function renderBodyScripts(): string
    {
        if (! static::shouldTrack()) {
            return '';
        }

        $settings = TrackingSetting::instance();
        $nonce = Vite::cspNonce() ?? '';
        $html = [];

        // 1. Google Tag Manager (Noscript)
        if ($settings->google_tag_manager_enabled && ! empty($settings->google_tag_manager_id) && static::consentAllows('all')) {
            $gtmId = htmlspecialchars($settings->google_tag_manager_id, ENT_QUOTES, 'UTF-8');
            $html[] = <<<HTML
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={$gtmId}"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
HTML;
        }

        // 2. Meta Pixel (Noscript)
        if ($settings->facebook_pixel_enabled && ! empty($settings->facebook_pixel_id) && static::consentAllows('marketing')) {
            $pixelId = htmlspecialchars($settings->facebook_pixel_id, ENT_QUOTES, 'UTF-8');
            $html[] = <<<HTML
<!-- Meta Pixel (noscript) -->
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id={$pixelId}&ev=PageView&noscript=1"
/></noscript>
HTML;
        }

        // 3. Custom Body Scripts
        if ($settings->custom_scripts_enabled && ! empty($settings->custom_body_scripts) && static::consentAllows('all')) {
            $customBody = static::injectNonceIntoScripts($settings->custom_body_scripts, $nonce);
            $html[] = "<!-- Custom Body Scripts -->\n{$customBody}";
        }

        return implode("\n", $html);
    }

    /**
     * Apply the current request's CSP nonce, replacing stale pasted nonces.
     */
    protected static function injectNonceIntoScripts(string $html, string $nonce): string
    {
        if (empty($nonce)) {
            return $html;
        }

        return preg_replace_callback('/<script\b((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>(.*?)(<\/script\s*>)/is', static function (array $matches) use ($nonce): string {
            $attributes = preg_replace_callback('/\s+([^\s=]+)(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s]+))?/', static fn (array $attribute): string => strtolower($attribute[1]) === 'nonce' ? '' : $attribute[0], $matches[1]);

            return '<script nonce="'.$nonce.'"'.$attributes.'>'.$matches[2].$matches[3];
        }, $html) ?? $html;
    }
}
