<?php

define('API_ACCESS', true);

require_once 'api/v1/config.php';

session_start();

require_once 'includes/import.php';
require_once 'includes/AuthMiddleware.php';

$auth = initAuth();
$currentUser = $auth->getCurrentUser();

$seo = [
    'title' => 'Conditions Générales d\'Utilisation - Pilote Shopping',
    'description' => 'Consultez les conditions générales d\'utilisation de Pilote Shopping. Règles d\'usage, droits et obligations.',
    'keywords' => 'CGU, conditions générales, utilisation, pilote shopping, règlement',
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
                    <i class="fas fa-file-contract text-blue-primary"></i>
                    <span class="text-white font-medium">Document légal</span>
                </div>

                <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold text-white mb-6">
                    Conditions Générales d'Utilisation
                </h1>

                <p class="text-xl text-gray-300 mb-8">
                    Les règles qui régissent l'utilisation de nos services
                </p>

                <div class="flex flex-col sm:flex-row items-center justify-center space-y-4 sm:space-y-0 sm:space-x-8 text-sm text-gray-400">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-calendar-alt text-blue-primary"></i>
                        <span>Dernière mise à jour : Septembre 2025</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-clock text-green-400"></i>
                        <span>Lecture : ~15 minutes</span>
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
                            <div class="text-gray-400 text-xs">Objet et champ d'application</div>
                        </a>
                        <a href="#article-2" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Article 2</div>
                            <div class="text-gray-400 text-xs">Compte Utilisateur</div>
                        </a>
                        <a href="#article-3" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Article 3</div>
                            <div class="text-gray-400 text-xs">Accès au Site</div>
                        </a>
                        <a href="#article-4" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Article 4</div>
                            <div class="text-gray-400 text-xs">Règles d'usage</div>
                        </a>
                        <a href="#article-5" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Article 5</div>
                            <div class="text-gray-400 text-xs">Produits numériques</div>
                        </a>
                        <a href="#article-6" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Article 6</div>
                            <div class="text-gray-400 text-xs">Lutte anti-fraude</div>
                        </a>
                    </div>
                </div>

                <div class="prose prose-invert prose-lg max-w-none">

                    <div id="article-1" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">Article 1 — Objet et champ d'application</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>1.1.</strong> Les présentes Conditions Générales d'Utilisation (« CGU ») régissent l'accès au site Pilote Shopping et l'usage des services associés (ci-après le « Site »).</p>

                            <p><strong>1.2.</strong> Tout accès ou usage du Site emporte acceptation pleine et entière des présentes CGU. À défaut, l'Utilisateur s'abstient d'utiliser le Site.</p>

                            <p><strong>1.3.</strong> Les conditions de vente, de livraison/activation, de garanties et de remboursement des produits numériques relèvent exclusivement des CGV et de la Politique de Remboursement (documents distincts).</p>
                        </div>
                    </div>

                    <div id="article-2" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">Article 2 — Compte Utilisateur : informations, exactitude, sécurité (KYC possible)</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>2.1. Inscription.</strong> Certaines fonctionnalités (historique, délivrance des produits numériques, support, fidélité) nécessitent la création d'un compte (« Compte »).</p>

                            <p><strong>2.2. Exactitude & sincérité.</strong> À l'inscription et à tout moment, l'Utilisateur s'engage à fournir des informations exactes, complètes et à jour (identité, e-mail, pays, le cas échéant informations de facturation). Toute information fausse, usurpée, incomplète ou obsolète peut entraîner des mesures immédiates (cf. art. 9).</p>

                            <p><strong>2.3. Mises à jour.</strong> L'Utilisateur met sans délai à jour ses informations via son Compte.</p>

                            <p><strong>2.4. Unicité & non-transférabilité.</strong> Un Compte est strictement personnel, nominatif et non transférable. La création de comptes multiples, le partage, le prêt ou la cession d'accès sont interdits.</p>

                            <p><strong>2.5. Âge.</strong> L'Utilisateur déclare avoir au moins 18 ans (ou utiliser le Site sous contrôle parental légalement valable).</p>

                            <p><strong>2.6. Sécurité des identifiants.</strong> L'Utilisateur est seul responsable de la confidentialité et de l'usage de ses identifiants. Il s'engage à (i) utiliser un mot de passe robuste et unique ; (ii) ne pas partager ses accès ; (iii) activer une authentification à deux facteurs (2FA) si disponible ; (iv) notifier immédiatement Pilote Shopping en cas de compromission suspectée.</p>

                            <p><strong>2.7. Contrôles KYC & blocage de sécurité.</strong> Dans le cadre de la lutte contre la fraude, Pilote Shopping peut vérifier l'exactitude des informations fournies, solliciter des justificatifs (contrôle KYC : pièce d'identité, justificatif de domicile, preuve de moyen de paiement, etc.) et bloquer temporairement une commande/le Compte à des fins de sécurité, y compris après passation de commande, le temps des vérifications (cf. art. 6).</p>

                            <p><strong>2.8. Preuve.</strong> Conformément aux articles 1366 et s. du Code civil, les écrits électroniques, journaux techniques, logs de connexion et d'actions peuvent valoir preuve entre les parties.</p>
                        </div>
                    </div>

                    <div id="article-3" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">Article 3 — Accès au Site et disponibilité</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>3.1.</strong> Le Site est en principe accessible 24/7, sous réserve d'opérations de maintenance, de mises à jour, d'actions de sécurité ou d'un cas de force majeure.</p>

                            <p><strong>3.2.</strong> Pilote Shopping peut modifier, suspendre ou limiter l'accès au Site pour raisons techniques ou de sécurité, sans indemnité.</p>
                        </div>
                    </div>

                    <div id="article-4" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">Article 4 — Règles d'usage et conduite interdite</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>4.1.</strong> L'Utilisateur s'interdit toute utilisation contraire aux lois et règlements, toute atteinte à l'intégrité, la disponibilité ou la sécurité du Site (art. 323-1 et s. Code pénal), l'ingénierie inverse, le scraping massif, l'usage de bots non autorisés, l'usurpation d'identité, la falsification de données, ou la diffusion de contenus illicites (diffamation, contrefaçon, incitation à la haine, etc.).</p>

                            <p><strong>4.2.</strong> En présence d'avis ou contenus générés par les Utilisateurs, Pilote Shopping applique une politique de modération et de retrait sur notification conformément à la LCEN (L. n° 2004-575, art. 6 I.5).</p>
                        </div>
                    </div>

                    <div id="article-5" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">Article 5 — Produits numériques (rappel)</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>5.1.</strong> Le Site commercialise des produits numériques.</p>

                            <p><strong>5.2.</strong> Les modalités d'acquisition, d'activation, de validité, de garanties et de remboursement relèvent exclusivement des CGV et de la Politique de Remboursement (documents séparés).</p>

                            <p><strong>5.3. Livraisons conditionnelles de clés/activations.</strong> Pour des raisons d'anti-fraude, Pilote Shopping peut différer l'affichage/la remise de clés/activations jusqu'à l'achèvement des contrôles de sécurité (cf. art. 6). En cas de non-coopération ou de risque sérieux, la commande peut être annulée et l'accès refusé, sans préjudice des voies de droit ultérieures.</p>
                        </div>
                    </div>

                    <div id="article-6" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">Article 6 — Lutte anti-fraude (LCB-FT), vérifications d'identité et contrôles</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>6.1. Principe.</strong> Pour protéger les Utilisateurs et la plateforme (vol de carte, revente illicite, blanchiment par achat-revente de codes), Pilote Shopping met en œuvre des contrôles proportionnés au risque, combinant analyses automatisées et revues humaines.</p>

                            <p><strong>6.2. Mesures possibles.</strong> Avant/après commande, Pilote Shopping peut, sans préavis : (a) bloquer temporairement une commande/livraison ; (b) exiger des justificatifs ; (c) effectuer des vérifications de cohérence (IP/pays, appareil, empreinte de navigateur, signaux anti-fraude) conformes au RGPD ; (d) annuler une opération suspecte et désactiver les accès associés ; (e) informer les autorités en cas de soupçon d'infraction.</p>

                            <p><strong>6.3. Paiements.</strong> Les paiements sont traités par des établissements de paiement/PSP régulés, assujettis LCB-FT (art. L.561-2 et s. du Code monétaire et financier). Ils peuvent conduire leurs propres vérifications KYC et demander des documents indépendamment de Pilote Shopping.</p>

                            <p><strong>6.4. Conséquences.</strong> À défaut de coopération ou en cas de risque avéré, Pilote Shopping peut refuser, annuler, bloquer ou retarder une commande, suspendre ou résilier le Compte (art. 9), sans indemnité.</p>

                            <p><strong>6.5. Statut.</strong> Pilote Shopping n'est pas un établissement de paiement, de monnaie électronique, ni un PSAN ; il agit comme plateforme d'édition et de distribution de produits numériques et collabore avec des prestataires de paiement régulés pour l'encaissement.</p>
                        </div>
                    </div>

                    <div id="article-7" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">Article 7 — Sécurité de l'information et protection des systèmes</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>7.1.</strong> Pilote Shopping met en œuvre des mesures techniques et organisationnelles raisonnables et adaptées (contrôle d'accès, chiffrement au repos/en transit lorsque pertinent, segmentation, supervision, journalisation, tests de sécurité périodiques, procédures de réponse à incident).</p>

                            <p><strong>7.2.</strong> L'Utilisateur s'engage à signaler sans délai tout incident, suspicion d'accès non autorisé ou vulnérabilité présumée.</p>

                            <p><strong>7.3.</strong> En cas de violation de données personnelles, Pilote Shopping respecte les obligations d'information et de notification prévues par les art. 33 et 34 du RGPD, le cas échéant.</p>
                        </div>
                    </div>

                    <div id="article-7bis" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">Article 7 bis — Tests intrusifs, exploitation et divulgation responsable des vulnérabilités</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p>L'Utilisateur s'interdit strictement (i) toute tentative d'intrusion, de déni de service, de brute force, de scan automatisé, de fuzzing, d'élévation de privilèges, d'exfiltration ou d'altération de données, ainsi que (ii) tout contournement des mesures techniques de protection, en violation notamment des art. 323-1 et s. du Code pénal.</p>

                            <p>Toute recherche de vulnérabilités n'est autorisée qu'avec l'accord écrit et préalable de Pilote Shopping, dans un périmètre défini. À défaut, tout test est réputé non autorisé.</p>

                            <p>En cas de découverte fortuite d'une faille, l'Utilisateur s'engage à : (a) s'abstenir de toute exploitation ; (b) documenter minimalement la faille ; (c) notifier sans délai Pilote Shopping via <strong>security@piloteshopping.com</strong> avec une description technique suffisante ; (d) supprimer sans délai tout élément obtenu par inadvertance.</p>

                            <p>La notification ne confère aucun droit de communication publique ; toute divulgation externe requiert l'accord écrit préalable de Pilote Shopping. La présente clause ne constitue pas une « safe harbor » générale.</p>
                        </div>
                    </div>

                    <div id="article-8" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">Article 8 — Propriété intellectuelle (éléments Pilote Shopping)</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>8.1.</strong> Le Site, son graphisme, ses marques, ses bases de données et son code (hors éléments tiers) sont protégés (droit d'auteur, marques, droits du producteur de bases). Toute reproduction, représentation, adaptation non autorisée est prohibée (ex. CPI art. L.335-2 s., L.713-2 s., L.342-1 s.).</p>

                            <p><strong>8.2. Licence d'usage limitée.</strong> Pilote Shopping concède une licence non exclusive, non transférable et révocable d'accès et d'usage personnel du Site, dans le strict cadre des présentes CGU.</p>

                            <p><strong>8.3. Contenus Utilisateur.</strong> En déposant un avis/contenu, l'Utilisateur concède à Pilote Shopping, pour le monde entier et pour la durée légale des droits, une licence non exclusive d'héberger, reproduire et publier ledit contenu aux seules fins d'exploitation du Site (avec droit de modération).</p>
                        </div>
                    </div>

                    <div id="article-8bis" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">Article 8 bis — Signes distinctifs et produits de tiers (absence de droits de Pilote Shopping)</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p>Les marques, logos, noms commerciaux, habillages, captures et, plus généralement, tous signes distinctifs ou contenus protégés affichés sur le Site aux fins d'identification, de description ou de compatibilité des produits numériques demeurent la propriété exclusive de leurs titulaires. Conformément au Code de la propriété intellectuelle (notamment art. L.713-2 et s., L.122-4, L.335-2 et s.), Pilote Shopping ne revendique aucun droit sur ces éléments, dont l'usage sur le Site est purement descriptif et n'implique ni affiliation, ni parrainage, ni approbation par les titulaires.</p>

                            <p>Les produits proposés à la vente sur le Site sont, pour tout ou partie, des produits de tiers (logiciels, abonnements, crédits, clés d'activation, contenus/services numériques) initialement conçus, édités et/ou commercialisés par la marque ou l'éditeur titulaire. Pilote Shopping n'est ni l'éditeur, ni le fabricant, ni détenteur des droits sur ces produits et ne se voit transférer aucun droit du seul fait de leur mise à disposition.</p>

                            <p>L'acquisition et l'utilisation de tels produits demeurent soumis aux licences, EULA et conditions de la marque/éditeur concerné. Aucune licence ou droit sur les signes distinctifs et contenus protégés de tiers n'est concédé à l'Utilisateur au-delà de ce qui est strictement nécessaire à l'usage du produit selon ses conditions propres.</p>

                            <p>En cas de réclamation d'un titulaire de droits, Pilote Shopping pourra, sans préavis, retirer le contenu litigieux, déférencer le produit ou suspendre l'accès aux éléments concernés. Les notifications PI peuvent être adressées à <strong>ip-notice@piloteshopping.com</strong> (joindre justificatifs de titularité et URL précise).</p>
                        </div>
                    </div>

                    <div id="article-9" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">Article 9 — Mesures et sanctions en cas de manquement</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>9.1.</strong> En cas de violation des CGU, de risque de fraude, d'atteinte à la sécurité ou de refus de coopérer à un contrôle, Pilote Shopping peut, sans préavis et à sa discrétion : (a) avertir l'Utilisateur ; (b) limiter certaines fonctionnalités ; (c) suspendre le Compte et/ou bloquer des commandes ; (d) résilier le Compte et supprimer l'accès aux actifs numériques non encore livrés ; (e) transmettre les informations utiles aux autorités compétentes.</p>

                            <p><strong>9.2.</strong> Ces mesures s'entendent sans préjudice des actions civiles et pénales.</p>
                        </div>
                    </div>

                    <div id="article-10" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">Article 10 — Données personnelles (rappel)</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>10.1.</strong> Les traitements de données sont décrits dans la Politique de Confidentialité (document séparé), conforme au RGPD (UE) 2016/679 et à la loi n° 78-17 modifiée.</p>

                            <p><strong>10.2. Bases légales.</strong> Les contrôles anti-fraude et vérifications d'identité peuvent reposer sur l'intérêt légitime (art. 6-1 f RGPD), l'exécution du contrat (art. 6-1 b), le respect d'obligations légales lorsqu'elles s'appliquent aux prestataires de paiement, et/ou le consentement lorsque requis.</p>

                            <p><strong>10.3. Conservation.</strong> Les durées sont limitées au nécessaire et précisées dans la Politique de Confidentialité. Les données techniques utiles à l'identification des contributeurs peuvent être conservées conformément à la LCEN art. 6 II.</p>

                            <p><strong>10.4. Droits.</strong> L'Utilisateur dispose des droits d'accès, rectification, effacement, limitation, opposition et portabilité, ainsi que du droit d'introduire une réclamation auprès de la CNIL.</p>
                        </div>
                    </div>

                    <div id="article-11" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">Article 11 — Services tiers et liens externes</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>11.1.</strong> Le Site peut intégrer des services tiers (hébergement, paiement, analytique, anti-fraude). Leur usage est régi par les conditions et politiques desdits tiers.</p>

                            <p><strong>11.2.</strong> Pilote Shopping ne peut être tenu responsable des dommages indirects ou résultant des services tiers, sous réserve des dispositions impératives applicables.</p>
                        </div>
                    </div>

                    <div id="article-12" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">Article 12 — Responsabilités et garanties</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>12.1. Obligations de moyens.</strong> Pilote Shopping déploie des moyens raisonnables pour assurer la sécurité et la disponibilité du Site, sans garantie d'absence totale d'erreur, d'interruption ou de vulnérabilité.</p>

                            <p><strong>12.2. Exclusions.</strong> Sont exclus les dommages indirects (perte de chance, de données, de profit), ainsi que les préjudices résultant d'un mauvais usage du Site par l'Utilisateur.</p>

                            <p><strong>12.3. Réseaux & force majeure.</strong> La responsabilité de Pilote Shopping ne saurait être engagée pour les faits d'un tiers, une panne réseau, ou un événement de force majeure.</p>
                        </div>
                    </div>

                    <div id="article-13" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">Article 13 — Modifications des CGU</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>13.1.</strong> Pilote Shopping peut modifier les CGU à tout moment pour motifs légitimes (évolutions légales/techniques/fonctionnelles).</p>

                            <p><strong>13.2.</strong> La version opposable est celle publiée sur le Site à la date d'usage. L'Utilisateur sera informé des changements significatifs par un moyen approprié (bandeau, notification, etc.).</p>
                        </div>
                    </div>

                    <div id="article-14" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">Article 14 — Résiliation et fermeture de Compte</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>14.1. À l'initiative de l'Utilisateur.</strong> Fermeture possible via le Compte ou en contactant le support, sous réserve d'absence de commandes en cours de contrôle.</p>

                            <p><strong>14.2. À l'initiative de Pilote Shopping.</strong> En cas de manquement, fraude ou risque, Pilote Shopping peut résilier le Compte selon l'art. 9.</p>

                            <p><strong>14.3.</strong> La résiliation n'affecte pas les clauses destinées à survivre (preuve, propriété intellectuelle, responsabilité, droit applicable).</p>
                        </div>
                    </div>

                    <div id="article-15" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">Article 15 — Notifications — Contact</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>15.1.</strong> Les notifications sont valablement faites par e-mail ou via le Compte.</p>

                            <p><strong>15.2.</strong> Contact support : <strong>support@piloteshopping.com</strong>.<br>
                            (Les Mentions légales, incluant l'identification de l'éditeur, figurent dans un document séparé.)</p>
                        </div>
                    </div>

                    <div id="article-16" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">Article 16 — Indivisibilité — Tolérance — Langue</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>16.1.</strong> Si une clause est jugée invalide, les autres demeurent en vigueur.</p>

                            <p><strong>16.2.</strong> Le fait de ne pas se prévaloir d'un droit ne vaut pas renonciation.</p>

                            <p><strong>16.3.</strong> En cas de traduction, la version française prévaut.</p>
                        </div>
                    </div>

                    <div id="article-17" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">Article 17 — Droit applicable — Juridictions compétentes</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>17.1.</strong> Les présentes CGU sont régies par le droit français.</p>

                            <p><strong>17.2.</strong> Tout litige relatif à l'usage du Site relève des juridictions françaises compétentes du ressort du siège de Pilote Shopping, sous réserve des dispositions impératives protectrices des consommateurs.</p>
                        </div>
                    </div>

                    <div id="annexe-a" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">Annexe A — Références légales utiles (indicatif)</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p><strong>Code civil :</strong> valeur probatoire de l'écrit électronique (art. 1366 et s.).</p>

                            <p><strong>Code pénal :</strong> atteintes aux systèmes d'information (art. 323-1 et s.).</p>

                            <p><strong>CPI :</strong> contrefaçon (L.335-2 s.), marques (L.713-2 s.), bases de données (L.342-1 s.).</p>

                            <p><strong>LCEN</strong> (L. n° 2004-575, art. 6) : responsabilité, conservation de données d'identification.</p>

                            <p><strong>RGPD</strong> (UE) 2016/679 & Loi Informatique et Libertés (n° 78-17 mod.).</p>

                            <p><strong>Code monétaire et financier :</strong> LCB-FT (art. L.561-2 et s.) pour les établissements de paiement/PSP partenaires.</p>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-blue-primary/10 to-purple-500/10 border border-blue-primary/30 rounded-2xl p-8 mt-12 text-center backdrop-blur-sm">
                    <h3 class="text-2xl font-bold text-white mb-4">
                        <i class="fas fa-question-circle text-blue-primary mr-3"></i>
                        Des questions sur nos CGU ?
                    </h3>
                    <p class="text-gray-300 mb-6">
                        Notre équipe support est là pour vous aider à comprendre vos droits et obligations.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="mailto:support@piloteshopping.com" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-primary to-purple-500 hover:from-blue-secondary hover:to-purple-600 text-white rounded-xl font-semibold transition-all duration-300 transform hover:scale-105">
                            <i class="fas fa-envelope mr-2"></i>
                            Nous contacter
                        </a>
                        <a href="/privacy" class="inline-flex items-center px-6 py-3 bg-dark-700/50 hover:bg-dark-600/50 text-white rounded-xl font-semibold border border-dark-600/50 hover:border-blue-primary/50 transition-all duration-300">
                            <i class="fas fa-shield-alt mr-2"></i>
                            Politique de confidentialité
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

.prose strong:has-text("@") {
    color: #3b82f6;
    background: rgba(59, 130, 246, 0.1);
    padding: 2px 6px;
    border-radius: 4px;
}
</style>

<?php
endHTMLPage();
?>