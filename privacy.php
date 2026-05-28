<?php

define('API_ACCESS', true);

require_once 'api/v1/config.php';

session_start();

require_once 'includes/import.php';
require_once 'includes/AuthMiddleware.php';

$auth = initAuth();
$currentUser = $auth->getCurrentUser();

$seo = [
    'title' => 'Politique de Confidentialité - Pilote Shopping',
    'description' => 'Découvrez comment nous protégeons vos données personnelles. Politique de confidentialité conforme au RGPD.',
    'keywords' => 'confidentialité, données personnelles, RGPD, privacy, protection',
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
                    <i class="fas fa-shield-alt text-blue-primary"></i>
                    <span class="text-white font-medium">Protection des données</span>
                </div>

                <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold text-white mb-6">
                    Politique de Confidentialité
                </h1>

                <p class="text-xl text-gray-300 mb-8">
                    Comment nous protégeons et utilisons vos données personnelles
                </p>

                <div class="flex flex-col sm:flex-row items-center justify-center space-y-4 sm:space-y-0 sm:space-x-8 text-sm text-gray-400">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-calendar-alt text-blue-primary"></i>
                        <span>Version : 1.0</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-shield-check text-green-400"></i>
                        <span>Conforme RGPD</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-clock text-green-400"></i>
                        <span>Lecture : ~12 minutes</span>
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
                            <div class="text-gray-400 text-xs">Responsable du traitement</div>
                        </a>
                        <a href="#section-2" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 2</div>
                            <div class="text-gray-400 text-xs">Données collectées</div>
                        </a>
                        <a href="#section-3" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 3</div>
                            <div class="text-gray-400 text-xs">Finalités du traitement</div>
                        </a>
                        <a href="#section-4" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 4</div>
                            <div class="text-gray-400 text-xs">Bases légales</div>
                        </a>
                        <a href="#section-5" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 5</div>
                            <div class="text-gray-400 text-xs">Destinataires des données</div>
                        </a>
                        <a href="#section-6" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 6</div>
                            <div class="text-gray-400 text-xs">Sécurité et anti-fraude</div>
                        </a>
                        <a href="#section-7" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 7</div>
                            <div class="text-gray-400 text-xs">Durée de conservation</div>
                        </a>
                        <a href="#section-8" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 8</div>
                            <div class="text-gray-400 text-xs">Droits des utilisateurs</div>
                        </a>
                        <a href="#section-9" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 9</div>
                            <div class="text-gray-400 text-xs">Transferts hors UE</div>
                        </a>
                        <a href="#section-10" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 10</div>
                            <div class="text-gray-400 text-xs">Modifications</div>
                        </a>
                        <a href="#section-11" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 11</div>
                            <div class="text-gray-400 text-xs">Contact</div>
                        </a>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-blue-primary/10 to-purple-500/10 border border-blue-primary/30 rounded-2xl p-8 mb-12 backdrop-blur-sm">
                    <div class="text-center mb-6">
                        <h2 class="text-2xl font-bold text-white mb-4">Notre engagement pour votre vie privée</h2>
                        <p class="text-gray-300 leading-relaxed">
                            La présente Politique de Confidentialité décrit les conditions dans lesquelles Pilote Shopping (ci-après « nous » ou « la plateforme ») collecte, utilise, conserve, protège et partage les données à caractère personnel des utilisateurs (ci-après « vous » ou « l'Utilisateur ») dans le cadre de son activité de vente de produits et services numériques.
                        </p>
                    </div>
                </div>

                <div class="prose prose-invert prose-lg max-w-none">

                    <div id="section-1" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">1. Responsable du traitement</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p>Le responsable du traitement des données personnelles est :</p>

                            <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                <p><strong>Pilote Shopping</strong><br>
                                67270 SCHWINDRATZHEIM<br>
                                E-mail de contact : <strong>privacy@piloteshopping.com</strong></p>
                            </div>
                        </div>
                    </div>

                    <div id="section-2" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">2. Données collectées</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p>Nous collectons uniquement les données nécessaires, de manière loyale et transparente, dans le respect du principe de minimisation (art. 5.1.c RGPD).</p>

                            <h3 class="text-xl font-bold text-white mt-8 mb-4">2.1. Données fournies directement par l'Utilisateur</h3>
                            <ul class="list-disc pl-6 space-y-2">
                                <li>Nom, prénom (lorsque requis).</li>
                                <li>Adresse e-mail (obligatoire pour la création de compte).</li>
                                <li>Identifiants de connexion (pseudo, mot de passe chiffré).</li>
                                <li>Adresse postale (si nécessaire pour facturation).</li>
                                <li>Moyen de paiement (via prestataire de paiement sécurisé).</li>
                                <li>Toute information transmise volontairement via le support (tickets, e-mails).</li>
                            </ul>

                            <h3 class="text-xl font-bold text-white mt-8 mb-4">2.2. Données collectées automatiquement</h3>
                            <ul class="list-disc pl-6 space-y-2">
                                <li>Données de connexion : adresses IP, logs, empreintes de navigateur/appareil, horodatages.</li>
                                <li>Cookies et technologies similaires (voir Politique Cookies séparée).</li>
                                <li>Informations techniques liées à la commande : pays, devise, langue, compatibilité, statut de livraison.</li>
                            </ul>

                            <h3 class="text-xl font-bold text-white mt-8 mb-4">2.3. Données de tiers</h3>
                            <ul class="list-disc pl-6 space-y-2">
                                <li>Informations relatives à l'exécution de commandes via les prestataires de paiement.</li>
                                <li>Informations nécessaires à la lutte contre la fraude (cf. art. 6).</li>
                            </ul>
                        </div>
                    </div>

                    <div id="section-3" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">3. Finalités du traitement</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p>Les données collectées sont utilisées pour :</p>

                            <ul class="list-disc pl-6 space-y-2">
                                <li><strong>Gestion des comptes utilisateurs :</strong> inscription, connexion, personnalisation.</li>
                                <li><strong>Exécution des commandes :</strong> traitement, facturation, livraison de produits numériques.</li>
                                <li><strong>Service client :</strong> assistance, gestion des litiges, support technique.</li>
                                <li><strong>Sécurité et lutte contre la fraude :</strong> prévention des paiements frauduleux, contrôles KYC éventuels, journalisation technique (cf. art. 6).</li>
                                <li><strong>Communication contractuelle :</strong> envoi d'e-mails relatifs aux commandes, abonnements, mises à jour des CGU/CGV.</li>
                                <li><strong>Amélioration du service :</strong> analyses statistiques anonymisées (trafic, performances).</li>
                                <li><strong>Obligations légales :</strong> conservation des factures, prévention du blanchiment (LCB-FT).</li>
                            </ul>
                        </div>
                    </div>

                    <div id="section-4" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">4. Bases légales du traitement</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p>Conformément à l'art. 6 RGPD, nos traitements reposent sur :</p>

                            <ul class="list-disc pl-6 space-y-2">
                                <li><strong>Exécution d'un contrat</strong> (gestion de commande, livraison, abonnement).</li>
                                <li><strong>Respect d'obligations légales</strong> (facturation, lutte contre la fraude, conservation des données de connexion).</li>
                                <li><strong>Intérêt légitime</strong> (sécurisation du Site, prévention des abus, amélioration continue).</li>
                                <li><strong>Consentement</strong> (communications marketing facultatives, utilisation de certains cookies).</li>
                            </ul>
                        </div>
                    </div>

                    <div id="section-5" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">5. Destinataires des données</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p>Vos données peuvent être transmises à :</p>

                            <ul class="list-disc pl-6 space-y-2">
                                <li>Équipes internes de Pilote Shopping strictement habilitées.</li>
                                <li>Prestataires techniques (hébergement, support, outils analytiques).</li>
                                <li>Prestataires de paiement (Stripe, PayPal, etc.) pour la gestion des transactions.</li>
                                <li>Autorités compétentes (fiscales, judiciaires, policières) si la loi l'exige ou en cas de procédure.</li>
                            </ul>

                            <div class="bg-green-500/10 border border-green-500/30 rounded-xl p-4 mt-6">
                                <p class="text-green-300 font-semibold">
                                    <i class="fas fa-shield-check mr-2"></i>
                                    Aucune donnée n'est vendue à des tiers.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div id="section-6" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">6. Sécurité et lutte contre la fraude</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p>Pilote Shopping met en place des mesures techniques et organisationnelles adaptées (chiffrement, segmentation, contrôle d'accès, journalisation, supervision).</p>

                            <p><strong>Dans le cadre de la prévention de la fraude :</strong></p>

                            <ul class="list-disc pl-6 space-y-2">
                                <li>Certaines commandes peuvent être bloquées et soumises à vérification manuelle.</li>
                                <li>Des justificatifs complémentaires (pièce d'identité, justificatif de domicile, preuve de paiement) peuvent être demandés.</li>
                                <li>Des informations techniques (IP, appareil, localisation approximative) peuvent être croisées pour détecter les anomalies.</li>
                                <li>En cas de fraude avérée, des données peuvent être transmises aux autorités compétentes (cf. art. 313-1 Code pénal, escroquerie).</li>
                            </ul>
                        </div>
                    </div>

                    <div id="section-7" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">7. Durée de conservation</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p>Les données sont conservées uniquement le temps nécessaire :</p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                                <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                    <h4 class="text-white font-semibold mb-2">Compte utilisateur</h4>
                                    <p class="text-sm">Tant que le compte est actif + 3 ans maximum après suppression (à des fins probatoires/gestion de litiges).</p>
                                </div>

                                <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                    <h4 class="text-white font-semibold mb-2">Facturation et comptabilité</h4>
                                    <p class="text-sm">10 ans (obligation légale, art. L123-22 Code de commerce).</p>
                                </div>

                                <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                    <h4 class="text-white font-semibold mb-2">Logs de connexion</h4>
                                    <p class="text-sm">1 an (LCEN art. 6-II).</p>
                                </div>

                                <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                    <h4 class="text-white font-semibold mb-2">Cookies analytiques/marketing</h4>
                                    <p class="text-sm">Durée précisée dans la Politique Cookies.</p>
                                </div>
                            </div>

                            <p class="mt-6">Au-delà, les données sont supprimées ou anonymisées.</p>
                        </div>
                    </div>

                    <div id="section-8" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">8. Droits des utilisateurs</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p>Conformément au RGPD (art. 15 à 22), vous disposez des droits suivants :</p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                                <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                    <h4 class="text-white font-semibold mb-2">
                                        <i class="fas fa-eye text-blue-400 mr-2"></i>Accès
                                    </h4>
                                    <p class="text-sm">Obtenir une copie des données vous concernant.</p>
                                </div>

                                <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                    <h4 class="text-white font-semibold mb-2">
                                        <i class="fas fa-edit text-green-400 mr-2"></i>Rectification
                                    </h4>
                                    <p class="text-sm">Corriger les données inexactes ou incomplètes.</p>
                                </div>

                                <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                    <h4 class="text-white font-semibold mb-2">
                                        <i class="fas fa-trash text-red-400 mr-2"></i>Effacement
                                    </h4>
                                    <p class="text-sm">« Droit à l'oubli ».</p>
                                </div>

                                <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                    <h4 class="text-white font-semibold mb-2">
                                        <i class="fas fa-pause text-yellow-400 mr-2"></i>Limitation
                                    </h4>
                                    <p class="text-sm">Limitation du traitement dans certains cas.</p>
                                </div>

                                <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                    <h4 class="text-white font-semibold mb-2">
                                        <i class="fas fa-ban text-orange-400 mr-2"></i>Opposition
                                    </h4>
                                    <p class="text-sm">Opposition au traitement, notamment à la prospection.</p>
                                </div>

                                <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                    <h4 class="text-white font-semibold mb-2">
                                        <i class="fas fa-download text-purple-400 mr-2"></i>Portabilité
                                    </h4>
                                    <p class="text-sm">Recevoir vos données dans un format structuré, ou demander leur transfert à un tiers.</p>
                                </div>
                            </div>

                            <div class="bg-blue-500/10 border border-blue-500/30 rounded-xl p-6 mt-8">
                                <h4 class="text-white font-semibold mb-4">Pour exercer vos droits :</h4>
                                <p class="mb-4">📧 <strong>privacy@piloteshopping.com</strong></p>
                                <p class="text-sm">Une réponse sera apportée sous 30 jours maximum.</p>
                                <p class="text-sm mt-4">Vous pouvez également saisir la CNIL (www.cnil.fr) si vous estimez que vos droits ne sont pas respectés.</p>
                            </div>
                        </div>
                    </div>

                    <div id="section-9" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">9. Transferts hors Union européenne</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p>Certains prestataires (hébergement, paiement, messagerie) peuvent impliquer des transferts de données hors UE.</p>

                            <p><strong>Ces transferts sont encadrés par :</strong></p>

                            <ul class="list-disc pl-6 space-y-2">
                                <li>des clauses contractuelles types (CCT) de la Commission européenne,</li>
                                <li>ou une décision d'adéquation (ex. Royaume-Uni, Suisse).</li>
                            </ul>
                        </div>
                    </div>

                    <div id="section-10" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">10. Modifications de la Politique</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p>Pilote Shopping peut modifier la présente Politique pour motifs légitimes (évolution légale, technique, fonctionnelle).</p>

                            <p>La version applicable est celle publiée sur le Site à la date de consultation.</p>

                            <p>Les modifications substantielles feront l'objet d'une notification (e-mail, bandeau, annonce Discord).</p>
                        </div>
                    </div>

                    <div id="section-11" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6">11. Contact</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p>Pour toute question sur la présente Politique :</p>

                            <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30 text-center">
                                <p class="text-lg">📧 <strong>privacy@piloteshopping.com</strong></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-blue-primary/10 to-purple-500/10 border border-blue-primary/30 rounded-2xl p-8 mt-12 text-center backdrop-blur-sm">
                    <h3 class="text-2xl font-bold text-white mb-4">
                        <i class="fas fa-question-circle text-blue-primary mr-3"></i>
                        Questions sur vos données personnelles ?
                    </h3>
                    <p class="text-gray-300 mb-6">
                        Notre équipe privacy est disponible pour vous accompagner dans la gestion de vos données personnelles.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="mailto:privacy@piloteshopping.com" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-primary to-purple-500 hover:from-blue-secondary hover:to-purple-600 text-white rounded-xl font-semibold transition-all duration-300 transform hover:scale-105">
                            <i class="fas fa-envelope mr-2"></i>
                            Contactez notre DPO
                        </a>
                        <a href="/terms" class="inline-flex items-center px-6 py-3 bg-dark-700/50 hover:bg-dark-600/50 text-white rounded-xl font-semibold border border-dark-600/50 hover:border-blue-primary/50 transition-all duration-300">
                            <i class="fas fa-file-contract mr-2"></i>
                            Voir les CGU
                        </a>
                    </div>

                    <div class="mt-8 pt-8 border-t border-dark-600/30">
                        <h4 class="text-lg font-bold text-white mb-4">Liens utiles</h4>
                        <div class="flex flex-wrap justify-center gap-4 text-sm">
                            <a href="https://www.cnil.fr" target="_blank" class="text-gray-400 hover:text-white transition-colors">
                                <i class="fas fa-external-link-alt mr-1"></i>CNIL
                            </a>
                            <a href="https://edpb.europa.eu" target="_blank" class="text-gray-400 hover:text-white transition-colors">
                                <i class="fas fa-external-link-alt mr-1"></i>EDPB
                            </a>
                            <a href="https://eur-lex.europa.eu/legal-content/FR/TXT/?uri=CELEX:32016R0679" target="_blank" class="text-gray-400 hover:text-white transition-colors">
                                <i class="fas fa-external-link-alt mr-1"></i>RGPD
                            </a>
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

    .prose h3 {
        font-size: 1.25rem;
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

.grid {
    display: grid;
}

@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-2 {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
}

.bg-dark-700\/30 {
    transition: all 0.3s ease;
}

.bg-dark-700\/30:hover {
    background-color: rgba(55, 65, 81, 0.5);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
}
</style>

<?php
endHTMLPage();
?>