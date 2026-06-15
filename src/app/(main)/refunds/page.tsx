import { LegalPage, LegalSection, LegalParagraph, LegalList } from "@/components/legal-page";

export const metadata = { title: "Politique de remboursement · Pilote Shopping" };

export default function RefundsPage() {
  return (
    <LegalPage
      title="Politique de remboursement"
      subtitle="Nos engagements et les conditions de remboursement applicables aux produits numériques achetés sur Pilote Shopping."
      updatedAt="15 juin 2026"
    >
      <LegalSection title="1. Principe (produits numériques)">
        <LegalParagraph>
          Les produits proposés par Pilote Shopping sont des biens numériques dématérialisés (clés de licence,
          comptes premium, accès et abonnements). Du fait de leur livraison immédiate et de leur nature, ces produits
          ne sont pas soumis au droit de rétractation classique une fois la clé ou l&apos;accès délivré.
        </LegalParagraph>
        <LegalParagraph>
          Pour autant, nous nous engageons à garantir un produit conforme et fonctionnel : un remboursement ou un
          remplacement est prévu dans les cas éligibles décrits ci-dessous.
        </LegalParagraph>
      </LegalSection>

      <LegalSection title="2. Cas éligibles au remboursement">
        <LegalParagraph>
          Une demande de remboursement ou de remplacement est recevable dans les situations suivantes :
        </LegalParagraph>
        <LegalList
          items={[
            <>Clé de licence défectueuse, invalide ou déjà utilisée à la livraison.</>,
            <>Produit non conforme à la description de la fiche (mauvaise région, version ou durée).</>,
            <>Accès impossible à activer malgré une utilisation correcte et l&apos;assistance du support.</>,
            <>Double facturation ou erreur de commande imputable à la plateforme.</>,
          ]}
        />
      </LegalSection>

      <LegalSection title="3. Cas non éligibles">
        <LegalParagraph>Aucun remboursement ne pourra être accordé dans les cas suivants :</LegalParagraph>
        <LegalList
          items={[
            <>Clé ou code déjà activé, révélé ou utilisé avec succès.</>,
            <>Erreur de choix de l&apos;utilisateur (produit, plateforme ou compatibilité non vérifiée).</>,
            <>Non-respect des conditions d&apos;utilisation du service tiers concerné.</>,
            <>Demande formulée hors du délai prévu à l&apos;article 4.</>,
          ]}
        />
      </LegalSection>

      <LegalSection title="4. Procédure">
        <LegalParagraph>
          Pour toute demande, contactez notre support dans un délai de <strong className="text-foreground">7
          jours</strong> suivant l&apos;achat, en précisant le numéro de commande et le problème rencontré
          (captures à l&apos;appui si possible) :
        </LegalParagraph>
        <LegalList
          items={[
            <>
              Par e-mail :{" "}
              <a href="mailto:support@piloteshopping.com" className="text-primary hover:underline">
                support@piloteshopping.com
              </a>
            </>,
            <>
              Via notre serveur Discord :{" "}
              <a href="https://discord.gg" target="_blank" rel="noreferrer" className="text-primary hover:underline">
                rejoindre le support
              </a>
            </>,
          ]}
        />
        <LegalParagraph>
          Notre équipe analyse chaque demande et propose, selon le cas, un remplacement de la clé ou un remboursement.
        </LegalParagraph>
      </LegalSection>

      <LegalSection title="5. Délais de remboursement">
        <LegalParagraph>
          Une fois la demande validée, le remboursement est effectué sous <strong className="text-foreground">3 à 14
          jours ouvrés</strong>. Le crédit du solde de votre portefeuille Pilote Shopping est immédiat ; un
          remboursement vers le moyen de paiement d&apos;origine peut nécessiter un délai supplémentaire propre à
          votre établissement bancaire.
        </LegalParagraph>
      </LegalSection>
    </LegalPage>
  );
}
