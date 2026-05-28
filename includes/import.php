<?php

$seo_defaults = [
    'title' => 'Pilote Shopping - E-commerce Premium',
    'description' => 'Découvrez Pilote Shopping, votre destination e-commerce premium pour les produits de qualité supérieure.',
    'keywords' => 'e-commerce, shopping, premium, qualité, pilote shopping',
    'author' => 'Pilote Shopping',
    'robots' => 'index, follow',
    'og_type' => 'website',
    'og_image' => '/assets/images/og-default.jpg',
    'twitter_card' => 'summary_large_image',
    'canonical' => ''
];

function setSEO($custom_seo = []) {
    global $seo_defaults;
    $seo = array_merge($seo_defaults, $custom_seo);
    
    if (empty($seo['title_suffix']) && !empty($custom_seo['title'])) {
        $seo['title'] = $custom_seo['title'] . ' - ' . SITE_NAME;
    }
    
    if (empty($seo['canonical'])) {
        $seo['canonical'] = SITE_URL . $_SERVER['REQUEST_URI'];
    }
    
    $seo['canonical'] = strtok($seo['canonical'], '?');
    return $seo;
}

function generateMetaTags($seo) {
    if (!class_exists('CsrfProtection')) {
        require_once __DIR__ . '/CsrfProtection.php';
    }
    $csrfToken = CsrfProtection::token();

    $html = '';
    $html .= '<meta charset="UTF-8">' . "\n";
    $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
    $html .= '<meta name="csrf-token" content="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    $html .= '<title>' . htmlspecialchars($seo['title']) . '</title>' . "\n";
    $html .= '<meta name="description" content="' . htmlspecialchars($seo['description']) . '">' . "\n";
    $html .= '<meta name="keywords" content="' . htmlspecialchars($seo['keywords']) . '">' . "\n";
    $html .= '<meta name="author" content="' . htmlspecialchars($seo['author']) . '">' . "\n";
    $html .= '<meta name="robots" content="' . htmlspecialchars($seo['robots']) . '">' . "\n";
    
    $html .= '<link rel="canonical" href="' . htmlspecialchars($seo['canonical']) . '">' . "\n";
    
        $html .= '<meta property="og:title" content="' . htmlspecialchars($seo['title']) . '">' . "\n";
    $html .= '<meta property="og:description" content="' . htmlspecialchars($seo['description']) . '">' . "\n";
    $html .= '<meta property="og:type" content="' . htmlspecialchars($seo['og_type']) . '">' . "\n";
    $html .= '<meta property="og:url" content="' . htmlspecialchars($seo['canonical']) . '">' . "\n";
    $html .= '<meta property="og:image" content="' . htmlspecialchars(SITE_URL . $seo['og_image']) . '">' . "\n";
    $html .= '<meta property="og:site_name" content="' . htmlspecialchars(SITE_NAME) . '">' . "\n";
    
        $html .= '<meta name="twitter:card" content="' . htmlspecialchars($seo['twitter_card']) . '">' . "\n";
    $html .= '<meta name="twitter:title" content="' . htmlspecialchars($seo['title']) . '">' . "\n";
    $html .= '<meta name="twitter:description" content="' . htmlspecialchars($seo['description']) . '">' . "\n";
    $html .= '<meta name="twitter:image" content="' . htmlspecialchars(SITE_URL . $seo['og_image']) . '">' . "\n";
    
        $html .= '<link rel="apple-touch-icon" sizes="180x180" href="/assets/images/apple-touch-icon.png">' . "\n";
    $html .= '<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon-32x32.png">' . "\n";
    $html .= '<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon-16x16.png">' . "\n";
    $html .= '<link rel="manifest" href="/site.webmanifest">' . "\n";
    $html .= '<meta name="theme-color" content="#7B61FF">' . "\n";
    
    return $html;
}

function generateTailwindConfig() {
    return '
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        dark: {
                            DEFAULT: "#0A0A0A",
                            900: "#0A0A0A",
                            800: "#121212",
                            700: "#1A1A1A",
                            600: "#242424",
                            500: "#2E2E2E",
                            400: "#383838",
                            300: "#4A4A4A",
                            200: "#6B6B6B",
                            100: "#8B8B8B",
                            surface: "#1A1A1A"
                        },
                        blue: {
                            primary: "#0EA5E9",
                            secondary: "#0284C7",
                            light: "#38BDF8",
                            dark: "#0369A1",
                            accent: "#06B6D4",
                        },
                        gray: {
                            text: "#F8FAFC",
                            secondary: "#CBD5E1",
                            muted: "#94A3B8",
                            border: "#334155",
                            light: "#475569",
                        }
                    },
                    fontFamily: {
                        sans: ["Inter", "system-ui", "sans-serif"],
                        mono: ["JetBrains Mono", "monospace"],
                    },
                    animation: {
                        "fade-in": "fadeIn 0.5s ease-in-out",
                        "slide-up": "slideUp 0.3s ease-out",
                        "pulse-slow": "pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite",
                    },
                    keyframes: {
                        fadeIn: {
                            "0%": { opacity: "0" },
                            "100%": { opacity: "1" },
                        },
                        slideUp: {
                            "0%": { transform: "translateY(10px)", opacity: "0" },
                            "100%": { transform: "translateY(0)", opacity: "1" },
                        }
                    },
                    boxShadow: {
                        "glow-blue": "0 0 20px rgba(14, 165, 233, 0.3)",
                        "glow-blue-lg": "0 0 30px rgba(14, 165, 233, 0.4)",
                        card: "0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)",
                        "card-hover": "0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)",
                    },
                    backdropBlur: {
                        xs: "2px",
                    }
                }
            }
        }
    </script>';
}

function generateCSSImports($additional_css = []) {
    $css_imports = [
        'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap',
        'https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&display=swap',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
        'https://unpkg.com/aos@2.3.1/dist/aos.css'
    ];
    
    $css_imports = array_merge($css_imports, $additional_css);
    
    $html = '';
    foreach ($css_imports as $css) {
        if (strpos($css, 'http') === 0) {
            $html .= '<link rel="stylesheet" href="' . $css . '">' . "\n";
        } else {
            $html .= '<link rel="stylesheet" href="/assets/css/' . $css . '">' . "\n";
        }
    }
    
    return $html;
}

function generateCustomCSS() {
    return '
    <style>
        :root {
            --bg-primary: #0A0A0A;
            --bg-secondary: #121212;
            --bg-surface: #1A1A1A;
            --bg-elevated: #242424;
            --border-primary: #2E2E2E;
            --border-light: #383838;
            --text-primary: #F8FAFC;
            --text-secondary: #CBD5E1;
            --text-muted: #94A3B8;
            --blue-primary: #0EA5E9;
            --blue-secondary: #0284C7;
            --blue-light: #38BDF8;
            --blue-dark: #0369A1;
        }
        
        body { 
            font-family: "Inter", sans-serif; 
            background-color: var(--bg-primary); 
            color: var(--text-primary); 
        }
        
        ::-webkit-scrollbar {
            width: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--bg-secondary);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--border-light);
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }
        
        .animate-fadeIn {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        .animate-slideUp {
            animation: slideUp 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { transform: translateY(10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .focus-ring {
            @apply focus:outline-none focus:ring-2 focus:ring-blue-primary focus:ring-offset-2 focus:ring-offset-dark-800;
        }
        
        .btn-primary {
            @apply bg-gradient-to-r from-blue-primary to-blue-secondary text-white font-medium px-6 py-3 rounded-lg transition-all duration-200 hover:from-blue-secondary hover:to-blue-dark hover:shadow-glow-blue focus-ring;
        }
        
        .btn-secondary {
            @apply bg-dark-600 text-gray-text border border-gray-border font-medium px-6 py-3 rounded-lg transition-all duration-200 hover:bg-dark-500 hover:border-gray-light focus-ring;
        }
        
        .input-field {
            @apply w-full px-4 py-3 bg-dark-700 border border-dark-500 rounded-lg text-gray-text placeholder-gray-muted transition-all duration-200 focus:bg-dark-600 focus:border-gray-light focus-ring;
        }
        
        .card {
            @apply bg-dark-800 border border-dark-500 rounded-xl shadow-card;
        }
        
        .card-hover {
            @apply hover:shadow-card-hover hover:border-dark-400 transition-all duration-200;
        }
        
        .bg-gradient-radial {
            background: radial-gradient(circle at center, rgba(14, 165, 233, 0.1) 0%, transparent 70%);
        }
        
        .overlay-pattern {
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(14, 165, 233, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(56, 189, 248, 0.08) 0%, transparent 50%);
        }
        
        .glow {
            box-shadow: 0 0 20px rgba(123, 97, 255, 0.3);
        }
        
        .glow:hover {
            box-shadow: 0 0 30px rgba(123, 97, 255, 0.5);
        }
        
        .glow-blue {
            box-shadow: 0 0 20px rgba(14, 165, 233, 0.3);
        }
        
        .glow-blue-lg {
            box-shadow: 0 0 30px rgba(14, 165, 233, 0.4);
        }
        
        .bg-dark-surface { background-color: var(--bg-surface); }
        .border-dark { border-color: var(--border-primary); }
        .text-purple { color: #7B61FF; }
        .text-blue { color: var(--blue-primary); }
        
        .fade-in-up {
            animation: fadeInUp 0.6s ease-out forwards;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .loading {
            position: relative;
            overflow: hidden;
        }
        
        .loading::after {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(14, 165, 233, 0.2), transparent);
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { left: -100%; }
            100% { left: 100%; }
        }
    </style>';
}

function generateJSImports($additional_js = []) {
    $js_imports = [
        'https://unpkg.com/aos@2.3.1/dist/aos.js',
        'https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js',
        'https://challenges.cloudflare.com/turnstile/v0/api.js',
        'https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js'
    ];
    
    $js_imports = array_merge($js_imports, $additional_js);
    
    $html = '';
    foreach ($js_imports as $js) {
        if (strpos($js, 'http') === 0) {
            if (strpos($js, 'alpinejs') !== false) {
                $html .= '<script src="' . $js . '" defer></script>' . "\n";
            } else {
                $html .= '<script src="' . $js . '"></script>' . "\n";
            }
        } else {
            $html .= '<script src="/assets/js/' . $js . '"></script>' . "\n";
        }
    }
    
    $html .= '<script>' . "\n";
    $html .= '(function(){' . "\n";
    $html .= '  var m=document.querySelector(\'meta[name="csrf-token"]\');' . "\n";
    $html .= '  var csrfToken=m?m.getAttribute("content"):"";' . "\n";
    $html .= '  window.CSRF_TOKEN=csrfToken;' . "\n";
    $html .= '  var origFetch=window.fetch;' . "\n";
    $html .= '  window.fetch=function(input,init){' . "\n";
    $html .= '    init=init||{};' . "\n";
    $html .= '    try{' . "\n";
    $html .= '      var method=(init.method||(input&&input.method)||"GET").toUpperCase();' . "\n";
    $html .= '      var url=typeof input==="string"?input:(input&&input.url)||"";' . "\n";
    $html .= '      var isSame=url.indexOf("/")===0||url.indexOf(location.origin)===0;' . "\n";
    $html .= '      if(isSame&&["POST","PUT","PATCH","DELETE"].indexOf(method)!==-1){' . "\n";
    $html .= '        init.headers=init.headers||{};' . "\n";
    $html .= '        if(init.headers instanceof Headers){if(!init.headers.has("X-CSRF-Token"))init.headers.set("X-CSRF-Token",csrfToken);}' . "\n";
    $html .= '        else if(Array.isArray(init.headers)){var f=init.headers.some(function(h){return h[0]&&h[0].toLowerCase()==="x-csrf-token"});if(!f)init.headers.push(["X-CSRF-Token",csrfToken]);}' . "\n";
    $html .= '        else if(!Object.keys(init.headers).some(function(k){return k.toLowerCase()==="x-csrf-token"}))init.headers["X-CSRF-Token"]=csrfToken;' . "\n";
    $html .= '      }' . "\n";
    $html .= '    }catch(e){}' . "\n";
    $html .= '    return origFetch.call(this,input,init);' . "\n";
    $html .= '  };' . "\n";
    $html .= '  if(window.XMLHttpRequest){' . "\n";
    $html .= '    var origOpen=XMLHttpRequest.prototype.open;' . "\n";
    $html .= '    var origSend=XMLHttpRequest.prototype.send;' . "\n";
    $html .= '    XMLHttpRequest.prototype.open=function(method,url){this._csrfMethod=method;this._csrfUrl=url;return origOpen.apply(this,arguments);};' . "\n";
    $html .= '    XMLHttpRequest.prototype.send=function(body){' . "\n";
    $html .= '      try{var m=(this._csrfMethod||"GET").toUpperCase();var u=this._csrfUrl||"";var s=u.indexOf("/")===0||u.indexOf(location.origin)===0;' . "\n";
    $html .= '      if(s&&["POST","PUT","PATCH","DELETE"].indexOf(m)!==-1)this.setRequestHeader("X-CSRF-Token",csrfToken);}catch(e){}' . "\n";
    $html .= '      return origSend.apply(this,arguments);' . "\n";
    $html .= '    };' . "\n";
    $html .= '  }' . "\n";
    $html .= '})();' . "\n";
    $html .= 'document.addEventListener("DOMContentLoaded", function() {' . "\n";
    $html .= '  if (typeof AOS !== "undefined") {' . "\n";
    $html .= '    AOS.init({' . "\n";
    $html .= '      duration: 800,' . "\n";
    $html .= '      easing: "ease-in-out",' . "\n";
    $html .= '      once: true' . "\n";
    $html .= '    });' . "\n";
    $html .= '  }' . "\n";
    $html .= '});' . "\n";
    $html .= '</script>' . "\n";
    
    return $html;
}

function generateStructuredData($type = 'website', $data = []) {
    $structured_data = [
        '@context' => 'https://schema.org',
        '@type' => ucfirst($type)
    ];
    
    switch ($type) {
        case 'website':
            $structured_data = array_merge($structured_data, [
                'name' => SITE_NAME,
                'url' => SITE_URL,
                'description' => 'Pilote Shopping - Service Premium (Netflix, Discord Nitro, etc.)',
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => SITE_NAME,
                    'url' => SITE_URL
                ]
            ]);
            break;
            
        case 'organization':
            $structured_data = array_merge($structured_data, [
                'name' => SITE_NAME,
                'url' => SITE_URL,
                'logo' => SITE_URL . '/assets/images/logo.png',
                'contactPoint' => [
                    '@type' => 'ContactPoint',
                    'email' => SITE_EMAIL,
                    'contactType' => 'customer service'
                ]
            ]);
            break;
            
        case 'product':
                        $structured_data = array_merge($structured_data, $data);
            break;
    }
    
    $structured_data = array_merge($structured_data, $data);
    
    return '<script type="application/ld+json">' . json_encode($structured_data, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}

function generateHTMLHead($custom_seo = [], $additional_css = [], $additional_js = [], $structured_data = []) {
    $seo = setSEO($custom_seo);
    
    $html = '<!DOCTYPE html>' . "\n";
    $html .= '<html lang="fr">' . "\n";
    $html .= '<head>' . "\n";
    $html .= generateMetaTags($seo);
    
    $html .= '<script src="https://cdn.tailwindcss.com"></script>' . "\n";
    $html .= generateTailwindConfig();
    $html .= generateCSSImports($additional_css);
    $html .= generateCustomCSS();
    
    if (!empty($structured_data)) {
        foreach ($structured_data as $type => $data) {
            $html .= generateStructuredData($type, $data);
        }
    } else {
        $html .= generateStructuredData('website');
    }
    
    $html .= '</head>' . "\n";
    
    return $html;
}

function generateFooterScripts($additional_js = []) {
    return generateJSImports($additional_js);
}

function startHTMLPage($custom_seo = [], $additional_css = [], $additional_js = [], $structured_data = []) {
    echo generateHTMLHead($custom_seo, $additional_css, $additional_js, $structured_data);
    echo '<body class="bg-dark text-white">' . "\n";
}

function endHTMLPage($additional_js = []) {
    echo generateFooterScripts($additional_js);
    echo '</body>' . "\n";
    echo '</html>' . "\n";
}

function getStatusColor($status) {
    $colors = [
        'pending' => 'text-yellow-400',
        'processing' => 'text-blue-400',
        'completed' => 'text-green-400',
        'cancelled' => 'text-red-400',
        'refunded' => 'text-purple-400',
        'delivered' => 'text-green-400',
        'failed' => 'text-red-400'
    ];
    
    return $colors[$status] ?? 'text-gray-400';
}

?>