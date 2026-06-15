<?php

define('API_ACCESS', true);

require_once 'api/v1/config.php';

session_start();

require_once 'includes/import.php';
require_once 'includes/AuthMiddleware.php';

$auth = initAuth();
$currentUser = $auth->getCurrentUser();

$seo = [
    'title' => 'Politique de Remboursement - Pilote Shopping',
    'description' => 'Consultez notre politique de remboursement. Conditions d\'échanges, remboursements et avoirs pour vos achats.',
    'keywords' => 'remboursement, échange, avoir, politique, pilote shopping',
    'og_image' => '/assets/images/og-legal.jpg'
];

startHTMLPage($seo);
?>


<?php require_once 'includes/menu.php'; ?>

<main id="main-content" class="min-h-screen">

    <section class="py-16 border-b border-dark-600/50">
        <div class="container mx-auto px-4">
            <div class="max-w-4xl mx-auto text-center">
                <div class="inline-flex items-center space-x-2 bg-dark-800/50 rounded-full px-6 py-2 mb-6 border border-dark-600/50">
                    <i class="fas fa-undo-alt text-blue-primary"></i>
                    <span class="text-white font-medium">Remboursements & Échanges</span>
                </div>

                <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold text-white mb-6">
                    Politique de Remboursement
                </h1>

                <p class="text-xl text-gray-300 mb-8">
                    Conditions de remboursement, d'échange et d'avoir applicables à vos achats
                </p>

                <div class="flex flex-col sm:flex-row items-center justify-center space-y-4 sm:space-y-0 sm:space-x-8 text-sm text-gray-400">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-calendar-alt text-blue-primary"></i>
                        <span>Version : 1.2</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-shield-check text-green-400"></i>
                        <span>Conforme Code conso.</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-clock text-green-400"></i>
                        <span>Lecture : ~18 minutes</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16">
        <div class="container mx-auto px-4">
            <div class="max-w-4xl mx-auto">

                <div class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-6 mb-12 backdrop-blur-sm">
                    <h3 class="text-xl font-bold text-white mb-4 flex items-center">
                        <i class="fas fa-list-ul text-blue-primary mr-3"></i>
                        Navigation rapide
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        <a href="#section-1" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 1</div>
                            <div class="text-gray-400 text-xs">Objet – Articulation</div>
                        </a>
                        <a href="#section-2" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 2</div>
                            <div class="text-gray-400 text-xs">Rappel légal – Rétractation</div>
                        </a>
                        <a href="#section-3" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 3</div>
                            <div class="text-gray-400 text-xs">Définitions</div>
                        </a>
                        <a href="#section-4" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 4</div>
                            <div class="text-gray-400 text-xs">Principes généraux</div>
                        </a>
                        <a href="#section-5" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 5</div>
                            <div class="text-gray-400 text-xs">Procédure – Dossier – Délais</div>
                        </a>
                        <a href="#section-6" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 6</div>
                            <div class="text-gray-400 text-xs">Délai raisonnable</div>
                        </a>
                        <a href="#section-7" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 7</div>
                            <div class="text-gray-400 text-xs">Cas éligibles</div>
                        </a>
                        <a href="#section-8" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 8</div>
                            <div class="text-gray-400 text-xs">Cas non éligibles</div>
                        </a>
                        <a href="#section-9" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 9</div>
                            <div class="text-gray-400 text-xs">Modalités de remboursement</div>
                        </a>
                        <a href="#section-10" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 10</div>
                            <div class="text-gray-400 text-xs">Lutte anti-fraude</div>
                        </a>
                        <a href="#section-11" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 11</div>
                            <div class="text-gray-400 text-xs">Abonnements</div>
                        </a>
                        <a href="#section-12" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 12</div>
                            <div class="text-gray-400 text-xs">Produits de tiers</div>
                        </a>
                        <a href="#section-13" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 13</div>
                            <div class="text-gray-400 text-xs">Prévention des abus</div>
                        </a>
                        <a href="#section-14" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 14</div>
                            <div class="text-gray-400 text-xs">Protection des données</div>
                        </a>
                        <a href="#section-15" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 15</div>
                            <div class="text-gray-400 text-xs">Dispositions finales</div>
                        </a>
                        <a href="#references-legales" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Références</div>
                            <div class="text-gray-400 text-xs">Références légales</div>
                        </a>
                    </div>
                </div>

                <div class="prose prose-invert prose-lg max-w-none">

                    <div id="section-1" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">1) Objet – Articulation avec CGV/CGU</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>1.1.</strong> La présente Politique encadre les remboursements, échanges et avoirs applicables aux commandes passées sur Pilote Shopping pour des produits et services numériques (clés/licences, crédits, accès, abonnements, comptes partagés).</p>

                            <p><strong>1.2.</strong> Elle s'applique en complément des CGV (vente) et des CGU (usage). En cas de contradiction, les CGV prévalent, sans préjudice des dispositions légales impératives (Code de la consommation, Code monétaire et financier, RGPD).</p>
                        </div>
                    </div>

                    <div id="section-2" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">2) Rappel légal — Absence de droit de rétractation (contenus/services numériques)</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>2.1.</strong> Conformément à l'article L221-28 du Code de la consommation, le droit de rétractation ne s'applique pas notamment aux :</p>

                            <ul class="list-disc pl-6 space-y-2">
                                <li>contenus numériques non fournis sur support matériel dont l'exécution débute immédiatement après l'achat, avec accord préalable et renonciation expresse du Client ;</li>
                                <li>services en ligne/abonnements dont l'exécution commence dès la confirmation du paiement.</li>
                            </ul>

                            <p><strong>2.2.</strong> En validant sa commande, le Client reconnaît le démarrage immédiat et renonce à son droit de rétractation pour ces produits/services.</p>
                        </div>
                    </div>

                    <div id="section-3" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">3) Définitions</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>Produit numérique :</strong> clé/licence, crédit, accès, contenu ou service dématérialisé.</p>

                            <p><strong>Abonnement :</strong> offre à durée déterminée/indéterminée (mensuelle, annuelle, etc.), avec reconduction éventuelle et/ou engagement.</p>

                            <p><strong>Plateforme/Éditeur tiers :</strong> service ou marque tiers dont les CGU/EULA conditionnent l'usage.</p>
                        </div>
                    </div>

                    <div id="section-4" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">4) Principes généraux de traitement des demandes</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>4.1. Priorité à la solution :</strong> en cas d'anomalie, Pilote Shopping privilégie la réparation (réémission de clé, correction d'accès, assistance) ou l'échange lorsque cela est possible et proportionné.</p>

                            <p><strong>4.2. Remboursement :</strong> envisagé lorsque (i) la délivrance/activation est impossible pour une cause imputable à Pilote Shopping et non résolue dans un délai raisonnable (cf. §6), ou (ii) en cas de non-conformité au sens légal lorsque réparation/échange est impossible, disproportionné ou non réalisé dans un délai raisonnable, ou (iii) dans les cas éligibles (cf. §7).</p>

                            <p><strong>4.3. Non-éligibilité :</strong> pas de remboursement dans les cas non éligibles (cf. §8), notamment lorsque l'incompatibilité ou la restriction était clairement indiquée sur la fiche produit et que l'environnement du Client ne s'y conforme pas (compatibilité, region-lock, type de compte, interdiction de VPN/proxy, DRM, etc.).</p>

                            <p><strong>4.4. Chargebacks :</strong> indépendamment de cette Politique, les rétrofacturations abusives peuvent entraîner suspension et frais (cf. CGV).</p>
                        </div>
                    </div>

                    <div id="section-5" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">5) Procédure – Dossier – Délais d'instruction</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>5.1. Ouverture :</strong> via https://piloteshopping.com/support (ou support@piloteshopping.com), en indiquant n° de commande, produit, description, captures d'écran/erreurs, et, si requis, éléments techniques (OS/version, pays/IP, état VPN, type de compte tiers).</p>

                            <p><strong>5.2. KYC/anti-fraude :</strong> en présence d'indicateurs de risque, Pilote Shopping peut requérir des justificatifs (pièce d'identité, justificatif de domicile, preuve du moyen de paiement). À défaut de coopération, l'instruction peut être suspendue (cf. §10).</p>

                            <p><strong>5.3. Délais indicatifs d'instruction :</strong></p>

                            <ul class="list-disc pl-6 space-y-2">
                                <li>Accusé de réception sous 48 h ouvrées ;</li>
                                <li>Diagnostic initial et plan d'action sous 7 jours ouvrés (hors dépendances critiques à un tiers/force majeure) ;</li>
                                <li>Traitement complet dans un délai raisonnable tel que défini au §6.</li>
                            </ul>
                        </div>
                    </div>

                    <div id="section-6" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">6) Notion de « délai raisonnable » (problèmes techniques)</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>6.1. Définition :</strong> sauf cas particuliers, le délai raisonnable pour mettre en œuvre les actions correctives n'excède pas 30 jours calendaires à compter de l'ouverture du dossier lorsque la résolution dépend majoritairement de Pilote Shopping.</p>

                            <p><strong>6.2. Dépendances à des tiers :</strong> lorsque la résolution dépend d'un éditeur/plateforme/PSP, le délai est celui strictement nécessaire à l'obtention de leurs éléments/levées techniques. Pilote Shopping fournit des mises à jour au moins tous les 10 jours calendaires et sollicite activement les tiers concernés.</p>

                            <p><strong>6.3. Force majeure / incidents majeurs :</strong> en cas d'événement extérieur, imprévisible et irrésistible (panne massive, incident de sécurité, décision administrative), le délai est suspendu pendant la durée de l'empêchement, avec information du Client.</p>

                            <p><strong>6.4. Issue :</strong> à l'issue du délai raisonnable, si la réparation/échange est impossible ou disproportionné, Pilote Shopping propose un remboursement, un avoir ou une solution équivalente conforme au droit de la conformité du numérique (art. L224-25-12 et s. C. conso.), selon le cas.</p>
                        </div>
                    </div>

                    <div id="section-7" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">7) Cas éligibles au remboursement (exemples)</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p>Sous réserve d'instruction et hors exclusions du §8 :</p>

                            <p><strong>7.1.</strong> Clé/licence erronée ou invalide fournie par Pilote Shopping et non utilisée, et impossibilité de réémission fonctionnelle dans le délai raisonnable.</p>

                            <p><strong>7.2.</strong> Échec de délivrance/activation imputable à Pilote Shopping malgré assistance et actions correctives menées dans le délai raisonnable.</p>

                            <p><strong>7.3.</strong> Double débit / paiement en doublon (remboursement du montant excédentaire).</p>

                            <p><strong>7.4.</strong> Non-conformité substantielle du produit par rapport à la description officielle de la fiche, lorsque réparation/échange ne peut aboutir dans le délai raisonnable (réf. L224-25-12 et s.).</p>

                            <p><strong>7.5.</strong> Indisponibilité définitive du produit (rupture irréversible côté éditeur) sans solution de remplacement équivalente acceptée par le Client.</p>
                        </div>
                    </div>

                    <div id="section-8" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">8) Cas non éligibles (exemples)</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>8.1.</strong> Incompatibilités/prérequis clairement indiqués (OS minimum, type de compte requis chez le tiers, region-lock, interdiction de VPN/proxy, DRM/anti-triche, simultanéité/profils, etc.) non respectés par l'environnement du Client.</p>

                            <p><strong>8.2.</strong> Clé/licence révélée et utilisée/associée (activation effectuée), sauf erreur imputable à Pilote Shopping ou non-conformité légale.</p>

                            <p><strong>8.3.</strong> Changement d'avis après fourniture/démarrage de l'exécution (L221-28).</p>

                            <p><strong>8.4.</strong> Suspension pour manquement aux ToS d'un tiers ou à nos CGU/CGV (perte d'accès imputable au Client).</p>

                            <p><strong>8.5.</strong> Modifications unilatérales d'un tiers postérieures à l'achat (API, DRM, zones, profils…), dès lors que le produit correspondait à la description au moment de la vente, sous réserve des règles impératives de conformité.</p>

                            <p><strong>8.6.</strong> Impossibilités liées à l'équipement/réseau du Client (appareil obsolète, réseau filtrant, appareil rooté/jailbreaké…) lorsque ces contraintes étaient prévisibles ou annoncées.</p>

                            <p><strong>8.7.</strong> Refus de coopérer : absence d'éléments techniques ou de justificatifs raisonnablement nécessaires.</p>
                        </div>
                    </div>

                    <div id="section-9" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">9) Modalités de remboursement (si approuvé)</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>9.1. Moyen :</strong> en priorité via le même moyen de paiement ; si impossible, avoir ou virement proposé.</p>

                            <p><strong>9.2. Montant :</strong> limité au prix payé pour le produit concerné, déduction faite des avantages consommés (crédits déjà utilisés, accès effectivement délivrés) et hors frais irréversibles facturés par des tiers lorsqu'ils ne peuvent être récupérés.</p>

                            <p><strong>9.3. Délais bancaires :</strong> l'exécution dépend du prestataire de paiement/banque (délais de compensation hors contrôle de Pilote Shopping).</p>
                        </div>
                    </div>

                    <div id="section-10" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">10) Lutte anti-fraude (LCB-FT), vérifications KYC et gel des fonds</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>10.1. Cadre légal :</strong> nos prestataires de paiement sont assujettis aux obligations de lutte contre le blanchiment de capitaux et le financement du terrorisme (LCB-FT) (art. L561-2 et s. Code monétaire et financier) et peuvent procéder à des vérifications KYC (Know Your Customer) et à des mesures conservatoires (réservations/indisponibilité temporaire de fonds).</p>

                            <p><strong>10.2. Suspension des livraisons :</strong> en cas de suspicion fondée (signal anti-fraude, incohérence des données, contestation suspecte, usurpation), Pilote Shopping peut bloquer la livraison ou l'accès jusqu'à la clôture des vérifications, sans remboursement pendant cette période.</p>

                            <p><strong>10.3. Indisponibilité des fonds :</strong> les fonds associés à la transaction peuvent être bloqués par le prestataire de paiement conformément à ses obligations légales. Aucun remboursement ni déblocage ne peut intervenir tant que :</p>

                            <ul class="list-disc pl-6 space-y-2">
                                <li>les vérifications KYC/anti-fraude ne sont pas finalisées ; et/ou</li>
                                <li>une instruction ou un gel demandé(e) par une autorité compétente est en vigueur (ex. TRACFIN).</li>
                            </ul>

                            <p><strong>10.4. Durée :</strong> aucun délai maximum légal unique n'est fixé pour ces mesures ; la durée dépend des vérifications nécessaires et/ou des instructions des autorités. À titre indicatif, les examens internes visant à lever les doutes sont conduits dans un délai cible de 30 jours calendaires, prolongeable si des éléments tiers sont requis (réponse PSP, éditeur, autorité).</p>

                            <p><strong>10.5. Issue :</strong></p>

                            <ul class="list-disc pl-6 space-y-2">
                                <li><strong>En cas de levée des doutes :</strong> reprise normale du traitement (livraison/échange/remboursement selon le cas).</li>
                                <li><strong>En cas de confirmation de risque ou d'instruction d'une autorité :</strong> Pilote Shopping peut annuler la commande, résilier l'accès et retenir l'impossibilité de rembourser tant que le PSP/autorités interdisent ou empêchent le dénouement, sans préjudice de poursuites civiles/pénales (ex. art. 313-1 C. pén. escroquerie).</li>
                            </ul>
                        </div>
                    </div>

                    <div id="section-11" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">11) Abonnements — Résiliation, prorata, révisions tarifaires</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>11.1. Hors engagement :</strong> résiliation à tout moment, effet à l'issue du cycle en cours (pas de remboursement du reliquat, sauf politique commerciale annoncée).</p>

                            <p><strong>11.2. Pendant engagement :</strong> redevabilité de tout ou partie des mensualités restantes, sauf motif légitime (ex. modification unilatérale substantielle non acceptée, défaut répété avéré) — cf. CGV.</p>

                            <p><strong>11.3. Révisions tarifaires :</strong> préavis ≥ 1 mois (e-mail / Site / Discord). Désaccord = résiliation sans frais avant effet ; à défaut, la poursuite vaut acceptation (cf. CGV).</p>

                            <p><strong>11.4. Suspension imputable au Client</strong> (ex. bannissement ToS d'un tiers/Discord) : pas de remboursement du reliquat.</p>
                        </div>
                    </div>

                    <div id="section-12" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">12) Produits de tiers – Marques – Non-affiliation</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>12.1.</strong> Certains produits sont initialement conçus, édités et/ou commercialisés par des tiers ; leurs licences/EULA/CGU s'appliquent et peuvent conditionner l'usage/remboursement.</p>

                            <p><strong>12.2.</strong> Pilote Shopping n'est ni l'éditeur ni détenteur des droits ; il n'est pas responsable des modifications/suspensions décidées par ces tiers, sous réserve des règles impératives de conformité.</p>

                            <p><strong>12.3.</strong> Les marques/logos demeurent la propriété de leurs titulaires ; usage descriptif sans affiliation/parrainage implicite.</p>
                        </div>
                    </div>

                    <div id="section-13" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">13) Prévention des abus — Rétrofacturations</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>13.1. Amiable préalable :</strong> le Client s'engage à rechercher d'abord une résolution amiable via le support.</p>

                            <p><strong>13.2. Rétrofacturations abusives :</strong> peuvent entraîner suspension du compte et frais de traitement (cf. CGV « Rétrofacturations » ; notamment L.133-18 à L.133-24 CMF).</p>

                            <p><strong>13.3. Signalement pénal :</strong> les fraudes/escroqueries peuvent être signalées aux autorités (art. 313-1 C. pén.).</p>
                        </div>
                    </div>

                    <div id="section-14" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">14) Protection des données (RGPD)</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>14.1.</strong> Les données relatives aux demandes (identité, logs, justificatifs) sont traitées pour instruire la demande, prévenir la fraude, satisfaire aux obligations légales.</p>

                            <p><strong>14.2.</strong> Conservation pendant la durée nécessaire à l'instruction et aux exigences probatoires, dans les limites légales.</p>

                            <p><strong>14.3. Droits et détails :</strong> Politique de Confidentialité.</p>
                        </div>
                    </div>

                    <div id="section-15" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">15) Dispositions finales</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>15.1. Hiérarchie :</strong> en cas de divergence avec la loi impérative, la loi prévaut.</p>

                            <p><strong>15.2. Évolutions :</strong> Pilote Shopping peut modifier la présente Politique pour motifs légitimes ; la version applicable est celle publiée à la date de la demande/commande, avec information en cas de changement substantiel.</p>

                            <p><strong>15.3. Médiation (consommateurs) :</strong> médiation gratuite en cas d'échec de la réclamation — [Coordonnées du médiateur à compléter] (art. L612-1 C. conso.).</p>

                            <p><strong>15.4. Contact :</strong> support@piloteshopping.com — https://piloteshopping.com/support</p>
                        </div>
                    </div>

                    <div id="references-legales" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">Références légales (indicatif)</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>Code de la consommation :</strong> L221-28 (rétractation – exceptions) ; L224-25-12 et s. (conformité contenus/services numériques) ; L612-1 (médiation).</p>

                            <p><strong>Code monétaire et financier :</strong> L561-2 et s. (LCB-FT, KYC), L.133-18 à L.133-24 (paiements par carte – contestations).</p>

                            <p><strong>Code pénal :</strong> 313-1 (escroquerie).</p>

                            <p><strong>Code civil :</strong> 1366 et s. (preuve électronique).</p>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-blue-primary/10 to-purple-500/10 border border-blue-primary/30 rounded-2xl p-8 mt-12 text-center backdrop-blur-sm">
                    <h3 class="text-2xl font-bold text-white mb-4">
                        <i class="fas fa-question-circle text-blue-primary mr-3"></i>
                        Besoin d'aide pour un remboursement ?
                    </h3>
                    <p class="text-gray-300 mb-6">
                        Notre équipe support est là pour vous accompagner dans toutes vos demandes de remboursement et d'échange.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="https://piloteshopping.com/support" target="_blank" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-primary to-purple-500 hover:from-blue-secondary hover:to-purple-600 text-white rounded-xl font-semibold transition-all duration-300 transform hover:scale-105">
                            <i class="fas fa-ticket-alt mr-2"></i>
                            Ouvrir un ticket
                        </a>
                        <a href="mailto:support@piloteshopping.com" class="inline-flex items-center px-6 py-3 bg-dark-700/50 hover:bg-dark-600/50 text-white rounded-xl font-semibold border border-dark-600/50 hover:border-blue-primary/50 transition-all duration-300">
                            <i class="fas fa-envelope mr-2"></i>
                            Nous contacter
                        </a>
                    </div>

                    <div class="mt-8 pt-8 border-t border-dark-600/30">
                        <h4 class="text-lg font-bold text-white mb-4">Délais de traitement</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                            <div class="bg-dark-700/30 rounded-lg p-3">
                                <div class="text-green-400 font-bold">48h</div>
                                <div class="text-gray-400">Accusé de réception</div>
                            </div>
                            <div class="bg-dark-700/30 rounded-lg p-3">
                                <div class="text-blue-400 font-bold">7 jours</div>
                                <div class="text-gray-400">Diagnostic initial</div>
                            </div>
                            <div class="bg-dark-700/30 rounded-lg p-3">
                                <div class="text-purple-400 font-bold">30 jours</div>
                                <div class="text-gray-400">Résolution complète</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once 'includes/footer.php'; ?>

<script>

document.addEventListener('DOMContentLoaded', function() {

    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);

            if (targetElement) {
                const headerHeight = 100;
                const targetPosition = targetElement.getBoundingClientRect().top + window.scrollY - headerHeight;

                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });

    function adjustContentMargin() {
        const header = document.querySelector('#menuContainer');
        const mainContent = document.getElementById('main-content');

        if (header && mainContent) {
            const headerHeight = header.offsetHeight;
            const headerTop = parseInt(window.getComputedStyle(header).top) || 8;
            mainContent.style.paddingTop = `${headerHeight + headerTop + 20}px`;
        }
    }

    adjustContentMargin();
    window.addEventListener('resize', adjustContentMargin);
});
</script>

<style>

html {
    scroll-behavior: smooth;
}

.prose a[href^="#"] {
    color: #3b82f6;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.3s ease;
}

.prose a[href^="#"]:hover {
    color: #60a5fa;
}

@media (max-width: 768px) {
    .prose {
        font-size: 14px;
    }

    .prose h2 {
        font-size: 1.5rem;
    }

    .container {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }
}

.prose strong {
    color: #ffffff;
    font-weight: 700;
}

.prose ul li {
    margin: 0.5rem 0;
}

.prose a[href^="mailto:"],
.prose a[href^="http"] {
    color: #3b82f6;
    background: rgba(59, 130, 246, 0.1);
    padding: 2px 6px;
    border-radius: 4px;
    text-decoration: none;
}

.prose a[href^="mailto:"]:hover,
.prose a[href^="http"]:hover {
    color: #60a5fa;
    background: rgba(59, 130, 246, 0.2);
}

.grid .bg-dark-700\/30 {
    transition: all 0.3s ease;
}

.grid .bg-dark-700\/30:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}
</style>

<?php
endHTMLPage();
?>