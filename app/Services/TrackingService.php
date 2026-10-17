<?php

namespace App\Services;

use App\Models\TrackingSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Vite;

class TrackingService
{
    /**
     * Determine if tracking scripts should be rendered for the current request.
     */
    public static function shouldTrack(): bool
    {
        $settings = TrackingSetting::instance();

        if ($settings->exclude_admins) {
            // Check if current request path is within admin area
            if (Request::is('admin*')) {
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
        if (! static::shouldTrack()) {
            if (Auth::guard('admin')->check() && ! Request::is('admin*')) {
                return "<!-- Tracking scripts suppressed: Logged in as administrator (to view, open in an incognito window or disable 'Exclude Authenticated Admins' in Admin Settings > Tracking & Pixels) -->";
            }

            return '';
        }

        $settings = TrackingSetting::instance();
        $nonce = Vite::cspNonce();
        $html = [];

        // 1. Microsoft Clarity
        if ($settings->clarity_enabled && ! empty($settings->clarity_project_id)) {
            $clarityId = htmlspecialchars($settings->clarity_project_id, ENT_QUOTES, 'UTF-8');
            $html[] = <<<HTML
<!-- Microsoft Clarity -->
<script type="text/javascript" nonce="{$nonce}">
    (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window, document, "clarity", "script", "{$clarityId}");
</script>
HTML;
        }

        // 2. Google Tag Manager (Head)
        if ($settings->google_tag_manager_enabled && ! empty($settings->google_tag_manager_id)) {
            $gtmId = htmlspecialchars($settings->google_tag_manager_id, ENT_QUOTES, 'UTF-8');
            $html[] = <<<HTML
<!-- Google Tag Manager -->
<script nonce="{$nonce}">(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{$gtmId}');</script>
HTML;
        }

        // 3. Google Analytics 4 (gtag.js)
        if ($settings->google_analytics_enabled && ! empty($settings->google_analytics_id)) {
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
        if ($settings->facebook_pixel_enabled && ! empty($settings->facebook_pixel_id)) {
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
        if ($settings->custom_scripts_enabled && ! empty($settings->custom_head_scripts)) {
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
        $nonce = Vite::cspNonce();
        $html = [];

        // 1. Google Tag Manager (Noscript)
        if ($settings->google_tag_manager_enabled && ! empty($settings->google_tag_manager_id)) {
            $gtmId = htmlspecialchars($settings->google_tag_manager_id, ENT_QUOTES, 'UTF-8');
            $html[] = <<<HTML
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={$gtmId}"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
HTML;
        }

        // 2. Meta Pixel (Noscript)
        if ($settings->facebook_pixel_enabled && ! empty($settings->facebook_pixel_id)) {
            $pixelId = htmlspecialchars($settings->facebook_pixel_id, ENT_QUOTES, 'UTF-8');
            $html[] = <<<HTML
<!-- Meta Pixel (noscript) -->
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id={$pixelId}&ev=PageView&noscript=1"
/></noscript>
HTML;
        }

        // 3. Custom Body Scripts
        if ($settings->custom_scripts_enabled && ! empty($settings->custom_body_scripts)) {
            $customBody = static::injectNonceIntoScripts($settings->custom_body_scripts, $nonce);
            $html[] = "<!-- Custom Body Scripts -->\n{$customBody}";
        }

        return implode("\n", $html);
    }

    /**
     * Ensure any inline or external <script> tags contain the CSP nonce if missing.
     */
    protected static function injectNonceIntoScripts(string $html, string $nonce): string
    {
        if (empty($nonce)) {
            return $html;
        }

        return preg_replace('/<script\b(?![^>]*\bnonce=)([^>]*)>/i', '<script nonce="'.$nonce.'"$1>', $html) ?? $html;
    }
}
