<?php

define('API_ACCESS', true);

require_once 'api/v1/config.php';

session_start();

require_once 'includes/import.php';
require_once 'includes/AuthMiddleware.php';

$auth = initAuth();
$currentUser = $auth->getCurrentUser();

$seo = [
    'title' => 'Conditions Générales de Vente - Pilote Shopping',
    'description' => 'Consultez les conditions générales de vente de Pilote Shopping. Modalités de commande, paiement, livraison et garanties.',
    'keywords' => 'CGV, conditions générales, vente, pilote shopping, commande',
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
                    <i class="fas fa-shopping-cart text-blue-primary"></i>
                    <span class="text-white font-medium">Document commercial</span>
                </div>

                <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold text-white mb-6">
                    Conditions Générales de Vente
                </h1>

                <p class="text-xl text-gray-300 mb-8">
                    Modalités de commande, paiement et livraison de nos services
                </p>

                <div class="flex flex-col sm:flex-row items-center justify-center space-y-4 sm:space-y-0 sm:space-x-8 text-sm text-gray-400">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-calendar-alt text-blue-primary"></i>
                        <span>Version : 1.1</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-clock text-green-400"></i>
                        <span>Lecture : ~20 minutes</span>
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
                        <a href="#article-1" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Article 1</div>
                            <div class="text-gray-400 text-xs">Objet – Champ d'application</div>
                        </a>
                        <a href="#article-2" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Article 2</div>
                            <div class="text-gray-400 text-xs">Définitions</div>
                        </a>
                        <a href="#article-3" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Article 3</div>
                            <div class="text-gray-400 text-xs">Prix – Taxes – Facturation</div>
                        </a>
                        <a href="#article-4" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Article 4</div>
                            <div class="text-gray-400 text-xs">Révisions tarifaires</div>
                        </a>
                        <a href="#article-5" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Article 5</div>
                            <div class="text-gray-400 text-xs">Commande – Validation – Preuve</div>
                        </a>
                        <a href="#article-6" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Article 6</div>
                            <div class="text-gray-400 text-xs">Modalités de paiement</div>
                        </a>
                        <a href="#article-7" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Article 7</div>
                            <div class="text-gray-400 text-xs">Durée – Engagement</div>
                        </a>
                        <a href="#article-8" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Article 8</div>
                            <div class="text-gray-400 text-xs">Livraison/Activation</div>
                        </a>
                        <a href="#article-9" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Article 9</div>
                            <div class="text-gray-400 text-xs">Absence de droit de rétractation</div>
                        </a>
                        <a href="#article-10" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Article 10</div>
                            <div class="text-gray-400 text-xs">Conformité – Défauts</div>
                        </a>
                        <a href="#article-11" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Article 11</div>
                            <div class="text-gray-400 text-xs">Abonnements tiers</div>
                        </a>
                        <a href="#article-12" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Article 12</div>
                            <div class="text-gray-400 text-xs">Non-paiement – Retards</div>
                        </a>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mt-3">
                        <a href="#article-13" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Article 13</div>
                            <div class="text-gray-400 text-xs">Rétrofacturations</div>
                        </a>
                        <a href="#article-14" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Article 14</div>
                            <div class="text-gray-400 text-xs">Produits de tiers</div>
                        </a>
                        <a href="#article-15" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Article 15</div>
                            <div class="text-gray-400 text-xs">Responsabilités – Garanties</div>
                        </a>
                        <a href="#article-16" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Article 16</div>
                            <div class="text-gray-400 text-xs">Médiation – Droit applicable</div>
                        </a>
                        <a href="#article-17" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Article 17</div>
                            <div class="text-gray-400 text-xs">Modification des CGV</div>
                        </a>
                        <a href="#article-18" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Article 18</div>
                            <div class="text-gray-400 text-xs">Dispositions diverses</div>
                        </a>
                    </div>
                </div>

                <div class="prose prose-invert prose-lg max-w-none">

                    <div id="article-1" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">1. Objet – Champ d'application</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>1.1.</strong> Les présentes conditions régissent la vente de produits et services numériques, y compris des abonnements (mensuels, annuels ou autre), proposés sur le site Pilote Shopping (le « Site »).</p>

                            <p><strong>1.2.</strong> Toute commande implique l'acceptation pleine et entière des présentes CGV. En cas de contradiction avec d'autres documents contractuels de Pilote Shopping, les CGV prévalent, sauf stipulation expresse contraire.</p>

                            <p><strong>1.3.</strong> Les CGU encadrent l'accès/usage du Site ; les présentes CGV encadrent la vente.</p>
                        </div>
                    </div>

                    <div id="article-2" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">2. Définitions</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>Client :</strong> toute personne ayant passé commande sur le Site.</p>

                            <p><strong>Abonnement :</strong> offre de fourniture de services/accès numériques pour une durée déterminée ou indéterminée, avec reconduction éventuelle.</p>

                            <p><strong>Produits numériques :</strong> clés d'activation, licences, crédits, accès, contenus ou services dématérialisés.</p>

                            <p><strong>Plateformes/éditeurs tiers :</strong> marques/éditeurs dont les CGU/EULA régissent les produits.</p>
                        </div>
                    </div>

                    <div id="article-3" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">3. Prix – Taxes – Facturation</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>3.1.</strong> Les prix sont indiqués en euros TTC, sauf indication contraire. Le prix applicable est celui affiché lors de la commande.</p>

                            <p><strong>3.2.</strong> Les prix peuvent varier selon le pays, la devise, la fiscalité ou l'offre. Des frais additionnels clairement indiqués peuvent s'appliquer.</p>

                            <p><strong>3.3.</strong> Une facture est fournie (espace compte et/ou e-mail).</p>
                        </div>
                    </div>

                    <div id="article-4" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">4. Révisions tarifaires (abonnements) – Information préalable – Résiliation sans frais</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>4.1.</strong> Pilote Shopping peut réviser les tarifs des abonnements (mensuels/annuels) pour tenir compte des coûts d'exploitation, variations de marché, taxes, inflation, parité monétaire, ou des modifications du service.</p>

                            <p><strong>4.2.</strong> Préavis d'1 mois minimum avant effet, via au moins un canal (e-mail, bandeau/mise à jour sur le Site et grille tarifaire, annonce officielle Discord).</p>

                            <p><strong>4.3. Principe :</strong> la révision s'applique au cycle suivant (renouvellement). Exception (cas limités) : évolution réglementaire/fiscale impérative, upgrade volontaire, option additionnelle, avec prorata et information préalable.</p>

                            <p><strong>4.4. Désaccord :</strong> droit de résiliation sans frais avant prise d'effet. À défaut, la poursuite vaut acceptation.</p>

                            <p><strong>4.5.</strong> Pour les offres avec engagement, toute évolution intra-période n'est possible que si clairement stipulée et assortie d'un droit de résiliation sans frais avant effet.</p>
                        </div>
                    </div>

                    <div id="article-5" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">5. Commande – Validation – Preuve</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>5.1.</strong> La commande est définitive après paiement effectif et confirmation.</p>

                            <p><strong>5.2.</strong> Pilote Shopping peut refuser, bloquer ou annuler une commande en cas de suspicion de fraude, d'anomalie manifeste, de non-respect des CGU/CGV, ou lorsqu'il existe une <strong>impossibilité objective d'exécution</strong> de la commande, telle que :</p>
                            <ul class="list-disc pl-6 space-y-2">
                                <li>indisponibilité technique ou rupture temporaire de clés/licences numériques,</li>
                                <li>restrictions légales, réglementaires ou contractuelles empêchant la délivrance,</li>
                                <li>incompatibilité connue et expressément indiquée sur la fiche produit,</li>
                                <li>cas de force majeure ou défaillance d'un fournisseur tiers.</li>
                            </ul>

                            <p><strong>5.3.</strong> Conformément aux art. 1366 et s. C. civ., l'acceptation électronique (logs, horodatage, double-clic, 3-D Secure) a valeur probante.</p>
                        </div>
                    </div>

                    <div id="article-6" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">6. Modalités de paiement</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>6.1.</strong> Paiement via des prestataires de paiement régulés.</p>

                            <p><strong>6.2.</strong> Le Client garantit disposer des autorisations pour utiliser le moyen choisi.</p>

                            <p><strong>6.3.</strong> En cas de refus/opposition/incident, la commande peut être annulée ou mise en attente de vérifications.</p>
                        </div>
                    </div>

                    <div id="article-7" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">7. Durée – Engagement – Renouvellement – Résiliation</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>7.1.</strong> La durée (avec ou sans reconduction tacite) et, le cas échéant, la période d’engagement sont précisées au moment de la commande.</p>

                            <p><strong>7.2. Reconduction tacite (consommateurs) :</strong> Pilote Shopping respecte les obligations d’information des art. L215-1 à L215-3 du Code de la consommation (rappel de la possibilité de ne pas reconduire, modalités de résiliation, délais).</p>

                            <p><strong>7.3. Changement d’offre :</strong> upgrade immédiat avec prorata ; downgrade applicable au prochain renouvellement (sauf mention expresse contraire).</p>

                            <p><strong>7.4. Résiliation hors engagement :</strong> possible à tout moment ; effet à l’issue du cycle en cours (sauf stipulation plus favorable). Lorsque la loi l’exige, un dispositif de résiliation en ligne est mis à disposition.</p>

                            <p><strong>7.5. Abonnements avec engagement :</strong> sauf motif légitime prévu par la loi ou par les présentes (par ex. <em>modification unilatérale substantielle non acceptée</em> du prix ou du service, <em>défaut de service avéré et répété</em> après mise en demeure), la résiliation anticipée entraîne l’exigibilité des sommes prévues jusqu’au terme de la période d’engagement <em>dans la limite permise par la loi</em>. Toute clause pénale ou frais de résiliation anticipée doivent rester proportionnés (art. 1231-5 C. civ.) et peuvent être modérés par le juge.</p>

                            <p><strong>7.6. Impayés répétés en période d’engagement :</strong> en cas d’échecs de prélèvements répétés et après <em>mise en demeure</em> restée infructueuse, Pilote Shopping peut : (i) suspendre l’accès ; (ii) prononcer la <em>résiliation anticipée pour faute</em> ; (iii) réclamer, le cas échéant, les sommes dues jusqu’au terme de l’engagement <em>dans les limites légales</em>. La suspension n’interrompt pas le cours de l’engagement.</p>

                            <p><strong>7.7. Procédure d’escalade type (indicatif) :</strong> 
                            <br>J+0 : premier échec de paiement → e-mail de relance + nouvelle tentative automatique ;
                            <br>J+5 : second échec → <em>mise en demeure</em> électronique (ou LRAR si nécessaire) informant de la suspension à défaut de régularisation ;
                            <br>J+10 : suspension des accès (sans suppression des données) ;
                            <br>J+30 : résiliation pour impayé si le solde n’est pas régularisé.
                            </p>

                            <p><strong>7.8. Effets de la résiliation :</strong> l’accès au service est coupé à la date d’effet ; les options/bonus liés à l’abonnement cessent. Les données éventuellement hébergées peuvent être supprimées à l’issue des délais légaux et contractuels de conservation (cf. Politique de confidentialité).</p>
                        </div>
                    </div>

                    <div id="article-8" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">8. Livraison/Activation – Disponibilité</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>8.1.</strong> Les produits numériques (clés, crédits, accès) sont délivrés électroniquement après paiement et, le cas échéant, contrôles de sécurité.</p>

                            <p><strong>8.2.</strong> Obligation de moyens quant à la disponibilité ; interruptions possibles (maintenance, sécurité, force majeure).</p>
                        </div>
                    </div>

                    <div id="article-9" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">9. Absence de droit de rétractation (produits numériques)</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p>Conformément à l'art. L221-28 C. conso., le droit de rétractation ne s'applique pas aux :</p>

                            <p><strong>a)</strong> contenus numériques non fournis sur support matériel, dont l'exécution débute immédiatement après l'achat et pour lesquels le Client a donné son accord préalable et renoncé expressément à ce droit ;</p>

                            <p><strong>b)</strong> services/abonnements en ligne dont l'exécution commence dès la confirmation du paiement.</p>

                            <p>Le Client reconnaît le démarrage immédiat et renonce au droit de rétractation pour ces produits/services.</p>
                        </div>
                    </div>

                    <div id="article-10" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">10. Conformité – Défauts – Assistance</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>10.1.</strong> Pour les produits/services fournis par des éditeurs/plateformes tiers, l'usage est soumis à leurs licences/EULA/CGU.</p>

                            <p><strong>10.2.</strong> En cas de difficulté d'activation ou d'accès, le Client contacte le support (https://piloteshopping.com/support). Pilote Shopping déploie des moyens raisonnables d'assistance et/ou d'orientation vers le tiers compétent.</p>

                            <p><strong>10.3. Conformité du numérique :</strong> sous réserve des dispositions impératives relatives aux contenus et services numériques (art. L224-25-12 et s. C. conso.).</p>
                        </div>
                    </div>

                    <div id="article-10bis" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">10 bis. Conditions du produit, compatibilité, prérequis et environnement d'utilisation</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>10.2.1. Information précontractuelle.</strong> Chaque fiche produit peut mentionner des prérequis (techniques, matériels, logiciels, versions d'OS, type de compte requis, zones géographiques/« region lock », exigences réseau/DRM, interdiction de VPN/proxy, nombre de connexions simultanées, politiques du fournisseur, etc.).</p>

                            <p><strong>10.2.2. Vérification par le Client.</strong> Il appartient au Client, avant la commande, de vérifier que son environnement (appareil, OS, compte, pays, réseau, paramètres de sécurité) satisfait les prérequis indiqués sur la fiche produit.</p>

                            <p><strong>10.2.3. Incompatibilité connue et annoncée.</strong> Lorsque une incompatibilité, restriction ou exigence était clairement indiquée sur la fiche produit avant l'achat, et que le Client ne satisfait pas ces conditions, Pilote Shopping ne peut être tenu responsable de l'impossibilité d'installer/activer/utiliser le produit, ni redevable d'un remboursement, sous réserve des dispositions impératives (cf. L224-25-12 et s., vice d'information, publicité trompeuse, erreur substantielle).</p>

                            <p><strong>10.2.4. Exemples non limitatifs :</strong></p>
                            <ul class="list-disc pl-6 space-y-2">
                                <li><strong>(a)</strong> Région/pays non compatible (clé/abonnement géo-restreint, exigence de moyen de paiement d'un pays donné) ;</li>
                                <li><strong>(b)</strong> Type de compte requis (compte « Premium/Family/Étudiant » chez le fournisseur) ;</li>
                                <li><strong>(c)</strong> Versions minimales d'OS/navigateurs ou matériel non supporté ;</li>
                                <li><strong>(d)</strong> DRM/anti-triche incompatibles avec virtualisation/émulation, appareils rootés/jailbreakés ;</li>
                                <li><strong>(e)</strong> Interdiction de VPN/proxy ou de bloqueurs perturbant l'authentification/licence ;</li>
                                <li><strong>(f)</strong> Politiques de plateforme (nombre de profils, restrictions de partage, simultanéité de flux).</li>
                            </ul>

                            <p><strong>10.2.5. Changements des tiers.</strong> Les éditeurs/plateformes tiers peuvent modifier leurs services (API, clients, politiques d'usage, zones, DRM) après l'achat. Sauf stipulation contraire contractuelle, Pilote Shopping n'assure pas la pérennité des fonctionnalités d'un service tiers ; il déploie des moyens raisonnables d'assistance (cf. art. 10.2) sans que sa responsabilité soit engagée lorsque l'incompatibilité découle d'un changement unilatéral d'un tiers.</p>

                            <p><strong>10.2.6. Exactitude de la fiche produit.</strong> Si une fiche produit s'avérait erronée, incomplète ou trompeuse quant aux prérequis compatibles et que cette présentation a déterminé le consentement du Client, Pilote Shopping mettra en œuvre une solution appropriée (correction, échange, geste commercial ou remboursement selon la Politique de Remboursement), sous réserve de preuve et de l'application des règles de conformité du numérique (L224-25-12 et s.).</p>

                            <p><strong>10.2.7. Preuves & diagnostic.</strong> Afin d'établir l'origine d'une incompatibilité, Pilote Shopping peut solliciter captures/logs/diagnostics (version OS, pays/IP, état VPN, type de compte, messages d'erreur). Le refus de coopérer peut retarder ou empêcher le traitement.</p>

                            <p><strong>10.2.8. Clés/licences déjà révélées.</strong> Une clé/licence révélée au Client (affichée/communiquée) n'est en principe ni échangeable ni remboursable, sauf dysfonctionnement avéré imputable à Pilote Shopping (erreur de clé, double attribution) ou inexactitude fautive de la fiche produit.</p>
                        </div>
                    </div>

                    <div id="article-11" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">11. Abonnements tiers – Comptes partagés – Moyens de paiement – Fraude</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>11.1. Comptes partagés :</strong> interdiction de (i) supprimer le profil d'un tiers ; (ii) modifier mot de passe/e-mail/offre ; (iii) annuler l'abonnement chez le tiers ; (iv) contourner des restrictions. Manquement = suspension/résiliation sans remboursement du reliquat.</p>

                            <p><strong>11.2. Moyens de paiement chez des tiers :</strong> tout paiement non autorisé après l'achat initial, récurrence non prévue, ou contournement de restrictions est prohibé.</p>

                            <p><strong>11.3. Sanctions & indemnités :</strong> en cas de fraude avérée, Pilote Shopping peut réclamer une indemnité forfaitaire proportionnée couvrant frais/préjudices, jusqu'au double du prix de l'abonnement concerné, sous réserve de modération judiciaire (art. 1231-5 C. civ.), sans préjudice d'actions pénales (art. 313-1 C. pén.).</p>

                            <p><strong>11.4. Non-respect des ToS de plateformes :</strong> la suspension d'un compte (ex. Discord) imputable au Client peut entraîner la perte d'accès au service correspondant sans remboursement du reliquat.</p>
                        </div>
                    </div>

                    <div id="article-12" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">12. Non-paiement – Retards – Conséquences</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>12.1. Suspension :</strong> à défaut de paiement à l’échéance, l’accès au service peut être suspendu jusqu’à régularisation, après <em>information préalable</em>. La suspension n’interrompt pas la durée de l’abonnement ni l’engagement éventuel.</p>

                            <p><strong>12.2. Mise en demeure et intérêts :</strong> en cas de non-paiement persistant après <em>mise en demeure</em>, des intérêts au <em>taux légal</em> peuvent être appliqués (art. 1231-6 C. civ.), à compter de la réception de la mise en demeure et jusqu’au complet paiement.</p>

                            <p><strong>12.3. Frais de paiement :</strong> lorsque la loi l’autorise, les <em>frais réellement facturés par le prestataire de paiement</em> (ex. rejets, représentations, contestations) peuvent être refacturés au Client, sur présentation et dans la limite des coûts effectivement supportés.</p>

                            <p><strong>12.4. Clients professionnels (B2B) :</strong> pour les transactions entre professionnels, et sans préjudice des intérêts de retard, une <em>indemnité forfaitaire de 40 €</em> pour frais de recouvrement est due de plein droit (art. L441-10 C. com.). Des frais complémentaires peuvent être réclamés lorsque les frais de recouvrement exposés sont supérieurs (sur justificatifs). <span class="text-gray-400">(Cette indemnité ne s’applique pas aux consommateurs.)</span></p>

                            <p><strong>12.5. Résiliation pour impayé :</strong> à défaut de régularisation sous 30 jours après mise en demeure, Pilote Shopping peut résilier le contrat pour impayé. En cas d’engagement, les conséquences financières suivent l’art. 7.5 (dans les limites légales et sous contrôle du juge).</p>

                            <p><strong>12.6. Réactivation :</strong> le service est réactivé après encaissement des sommes dues. Des <em>frais de remise en service</em> raisonnables peuvent être appliqués lorsqu’ils correspondent à des coûts réels de remise en conformité (lorsque la loi l’autorise).</p>

                            <p><strong>12.7. Recouvrement amiable puis judiciaire :</strong> à défaut d’accord amiable, le dossier peut être confié à un prestataire de recouvrement dûment mandaté. Pilote Shopping se réserve la possibilité d’engager une procédure d’<em>injonction de payer</em> ou toute action utile devant la juridiction compétente.</p>

                            <p><strong>12.8. Données et lutte anti-fraude :</strong> en cas d’impayé/contestation, les données strictement nécessaires (logs, empreintes de paiement, adresses, IP) peuvent être conservées et communiquées dans le cadre légal aux fins de prévention et de traitement de la fraude, conformément à la Politique de confidentialité.</p>

                            <p><strong>12.9. Réserves d’ordre public :</strong> toutes les mesures ci-dessus s’appliquent <em>sans préjudice</em> des dispositions impératives protectrices des consommateurs ou d’éventuelles circonstances légitimes (difficultés avérées, procédure de surendettement, etc.). Pilote Shopping peut adapter les délais et modalités à titre gracieux.</p>
                        </div>
                    </div>

                    <div id="article-13" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">13. Rétrofacturations (« chargebacks ») – Contestations de paiement</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>13.1. Amiable d'abord :</strong> contacter le support (https://piloteshopping.com/support).</p>

                            <p><strong>13.2. Chargebacks non justifiés/abusifs :</strong> (a) suspension immédiate du compte ; (b) frais fixes de 25 € (coûts réellement supportés), lorsque la loi le permet ; (c) contestation formelle avec preuves.</p>

                            <p><strong>13.3. Cadre légal :</strong> L.133-18 à L.133-24 C. mon. fin. (dont L.133-19). En cas de fraude/abus, Pilote Shopping se réserve des actions civiles/pénales (not. art. 313-1 C. pén.).</p>

                            <p><strong>13.4. Données en cas de litige :</strong> IP, empreinte navigateur, adresse postale, logs peuvent être conservés et communiqués strictement aux fins de prévention/traitement de la fraude, selon la loi et la Politique de Confidentialité.</p>
                        </div>
                    </div>

                    <div id="article-14" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">14. Produits de tiers – Marques – Non-affiliation</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>14.1.</strong> Les produits vendus peuvent être des produits de tiers (marques/éditeurs). Ils sont initialement conçus, édités et/ou commercialisés par ces tiers. Pilote Shopping n'est ni l'éditeur, ni détenteur des droits.</p>

                            <p><strong>14.2. Marques/logos :</strong> propriété de leurs titulaires ; usage descriptif uniquement ; pas d'affiliation/parrainage/approbation implicite.</p>

                            <p><strong>14.3.</strong> L'usage des produits de tiers est régi par leurs licences/EULA/CGU ; le Client s'y conforme. Pilote Shopping n'est pas responsable des modifications/suspensions décidées par ces tiers.</p>
                        </div>
                    </div>

                    <div id="article-15" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">15. Responsabilités – Garanties</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>15.1.</strong> Pilote Shopping est tenu d'obligations de moyens (fourniture et assistance).</p>

                            <p><strong>15.2.</strong> Pas de responsabilité pour dommages indirects (perte de chance, données, profits), ni pour les défaillances imputables (i) au Client (usage contraire aux CGU/CGV), (ii) aux tiers, (iii) à la force majeure.</p>

                            <p><strong>15.3.</strong> Les garanties légales/commerciales applicables sont rappelées, le cas échéant, dans les documents des éditeurs/plateformes.</p>
                        </div>
                    </div>

                    <div id="article-16" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">16. Médiation de la consommation – Droit applicable – Juridiction</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>16.1.</strong> Médiation gratuite possible : [Coordonnées du médiateur à compléter], selon L612-1 C. conso.</p>

                            <p><strong>16.2.</strong> Droit applicable : droit français. Juridictions françaises compétentes, sans préjudice des règles protectrices du consommateur.</p>
                        </div>
                    </div>

                    <div id="article-17" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">17. Modification des CGV</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p>Pilote Shopping peut modifier les CGV pour motif légitime (évolutions légales/techniques/fonctionnelles). Version opposable : celle en vigueur à la commande ; pour les abonnements, modifications substantielles notifiées ≥ 1 mois avant effet avec droit de résiliation sans frais (cf. art. 4).</p>
                        </div>
                    </div>

                    <div id="article-18" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">18. Dispositions diverses</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>18.1. Indivisibilité :</strong> la nullité d'une clause n'affecte pas les autres.</p>

                            <p><strong>18.2. Tolérance :</strong> l'absence d'exercice d'un droit ne vaut pas renonciation.</p>

                            <p><strong>18.3. Preuve électronique :</strong> l'écrit électronique, les logs et horodatages valent preuve.</p>
                        </div>
                    </div>

                    <div id="references-legales" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">Références légales clés (indicatif)</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>Code de la consommation :</strong> L215-1 à L215-3 (tacite reconduction) ; L221-28 (rétractation – exceptions) ; L224-25-12 et s. (conformité des contenus/services numériques) ; L612-1 (médiation).</p>

                            <p><strong>Code monétaire et financier :</strong> L.133-18 à L.133-24 (paiements – contestations).</p>

                            <p><strong>Code pénal :</strong> 313-1 (escroquerie).</p>

                            <p><strong>Code civil :</strong> 1231-5 (clause pénale – modération), 1366 et s. (preuve électronique).</p>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-blue-primary/10 to-purple-500/10 border border-blue-primary/30 rounded-2xl p-8 mt-12 text-center backdrop-blur-sm">
                    <h3 class="text-2xl font-bold text-white mb-4">
                        <i class="fas fa-question-circle text-blue-primary mr-3"></i>
                        Des questions sur nos CGV ?
                    </h3>
                    <p class="text-gray-300 mb-6">
                        Notre équipe support est disponible pour vous accompagner dans vos achats et répondre à vos questions.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="mailto:support@piloteshopping.com" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-primary to-purple-500 hover:from-blue-secondary hover:to-purple-600 text-white rounded-xl font-semibold transition-all duration-300 transform hover:scale-105">
                            <i class="fas fa-envelope mr-2"></i>
                            Support client
                        </a>
                        <a href="/terms" class="inline-flex items-center px-6 py-3 bg-dark-700/50 hover:bg-dark-600/50 text-white rounded-xl font-semibold border border-dark-600/50 hover:border-blue-primary/50 transition-all duration-300">
                            <i class="fas fa-file-contract mr-2"></i>
                            Conditions d'utilisation
                        </a>
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
</style>

<?php
endHTMLPage();
?>