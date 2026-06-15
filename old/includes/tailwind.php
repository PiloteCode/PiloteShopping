<?php

function getTailwindConfig() {
    return "
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {

                        dark: {
                            900: '#0A0A0A',    // Noir principal
                            800: '#121212',    // Surface principale
                            700: '#1A1A1A',    // Surface élevée
                            600: '#242424',    // Surface interactive
                            500: '#2E2E2E',    // Bordures
                            400: '#383838',    // Bordures claires
                            300: '#4A4A4A',    // Texte secondaire
                            200: '#6B6B6B',    // Texte tertiaire
                            100: '#8B8B8B',    // Texte désactivé
                        },
                        brown: {
                            900: '#0F0D0A',    // Brun très foncé
                            800: '#1A1510',    // Brun foncé
                            700: '#252018',    // Brun moyen foncé
                            600: '#2F2A20',    // Brun moyen
                            500: '#3A3428',    // Brun
                            400: '#4A4235',    // Brun clair
                        },
                        blue: {
                            primary: '#0EA5E9',     // Bleu principal
                            secondary: '#0284C7',   // Bleu secondaire
                            light: '#38BDF8',       // Bleu clair
                            dark: '#0369A1',        // Bleu foncé
                            accent: '#06B6D4',      // Cyan accent
                        },
                        gray: {
                            text: '#F8FAFC',        // Texte principal
                            secondary: '#CBD5E1',   // Texte secondaire
                            muted: '#94A3B8',       // Texte atténué
                            border: '#334155',      // Bordures
                            light: '#475569',       // Bordures claires
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(10px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        }
                    },
                    boxShadow: {
                        'glow-blue': '0 0 20px rgba(14, 165, 233, 0.3)',
                        'glow-blue-lg': '0 0 30px rgba(14, 165, 233, 0.4)',
                        'card': '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
                        'card-hover': '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
                    },
                    backdropBlur: {
                        xs: '2px',
                    }
                }
            }
        }
    </script>
    ";
}

function getCustomCSS() {
    return "
    <style>
        
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&display=swap');
        
        
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
        
        
        .glow-blue {
            box-shadow: 0 0 20px rgba(14, 165, 233, 0.3);
        }
        
        .glow-blue-lg {
            box-shadow: 0 0 30px rgba(14, 165, 233, 0.4);
        }
        
        
        .overlay-pattern {
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(14, 165, 233, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(56, 189, 248, 0.08) 0%, transparent 50%);
        }
        
        
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
            content: '';
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
    </style>
    ";
}

function getTailwindConfigAndCSS() {
    return getTailwindConfig() . getCustomCSS();
}
?>