import { LegalPage, LegalSection, LegalParagraph, LegalList } from "@/components/legal-page";

export const metadata = { title: "Conditions Générales · Pilote Shopping" };

export default function TermsPage() {
  return (
    <LegalPage
      title="Conditions Générales d'Utilisation et de Vente"
      subtitle="Les présentes conditions régissent l'utilisation du site Pilote Shopping et la vente de produits numériques aux utilisateurs."
      updatedAt="15 juin 2026"
    >
      <LegalSection title="1. Objet">
        <LegalParagraph>
          Les présentes Conditions Générales d&apos;Utilisation et de Vente (ci-après « CGU/CGV ») ont pour objet de
          définir les modalités d&apos;accès et d&apos;utilisation du site Pilote Shopping ainsi que les conditions
          de vente des produits numériques proposés. Toute commande implique l&apos;acceptation pleine et entière des
          présentes conditions.
        </LegalParagraph>
      </LegalSection>

      <LegalSection title="2. Produits numériques">
        <LegalParagraph>
          Pilote Shopping commercialise exclusivement des produits numériques : clés de licence, comptes premium,
          accès API et abonnements à des services tiers. Ces produits sont dématérialisés et délivrés par voie
          électronique, sans support physique.
        </LegalParagraph>
        <LegalParagraph>
          Les caractéristiques essentielles de chaque produit (type d&apos;accès, durée, compatibilité) sont
          présentées sur la fiche correspondante. Il appartient à l&apos;utilisateur de vérifier la compatibilité du
          produit avec ses besoins avant tout achat.
        </LegalParagraph>
      </LegalSection>

      <LegalSection title="3. Commande">
        <LegalParagraph>
          La commande s&apos;effectue depuis l&apos;espace client après sélection des produits et validation du
          panier. La vente est réputée conclue à la confirmation du paiement. Pilote Shopping se réserve le droit
          d&apos;annuler ou de refuser toute commande présentant un caractère anormal, frauduleux ou de mauvaise foi.
        </LegalParagraph>
      </LegalSection>

      <LegalSection title="4. Prix et paiement">
        <LegalParagraph>
          Les prix sont indiqués en euros, toutes taxes comprises (TTC). Pilote Shopping se réserve le droit de
          modifier ses prix à tout moment, étant entendu que le produit est facturé au tarif en vigueur lors de la
          validation de la commande.
        </LegalParagraph>
        <LegalList
          items={[
            <>Moyens de paiement : carte bancaire, portefeuille électronique et solde de compte.</>,
            <>Les transactions sont sécurisées et chiffrées de bout en bout.</>,
            <>La commande n&apos;est traitée qu&apos;après encaissement effectif du paiement.</>,
          ]}
        />
      </LegalSection>

      <LegalSection title="5. Livraison">
        <LegalParagraph>
          La livraison des produits numériques est automatique et instantanée : les clés et accès sont mis à
          disposition dans l&apos;espace client immédiatement après confirmation du paiement, et un récapitulatif est
          envoyé par e-mail. En cas de délai exceptionnel, le support intervient dans les meilleurs délais.
        </LegalParagraph>
      </LegalSection>

      <LegalSection title="6. Droit de rétractation pour les produits numériques">
        <LegalParagraph>
          Conformément à l&apos;article L.221-28 du Code de la consommation, le droit de rétractation ne peut être
          exercé pour la fourniture d&apos;un contenu numérique non fourni sur support matériel dont l&apos;exécution
          a commencé avec l&apos;accord préalable exprès du consommateur.
        </LegalParagraph>
        <LegalParagraph>
          En validant sa commande, l&apos;utilisateur reconnaît expressément renoncer à son droit de rétractation dès
          la mise à disposition de la clé ou de l&apos;accès. Les modalités spécifiques de remboursement (clé
          défectueuse, produit non conforme) sont détaillées dans notre politique de remboursement.
        </LegalParagraph>
      </LegalSection>

      <LegalSection title="7. Responsabilité">
        <LegalParagraph>
          Pilote Shopping est tenu à une obligation de moyens. Sa responsabilité ne saurait être engagée en cas de
          mauvaise utilisation du produit, de non-respect des conditions d&apos;utilisation du service tiers
          concerné, ou de force majeure. La responsabilité de l&apos;éditeur est en tout état de cause limitée au
          montant de la commande concernée.
        </LegalParagraph>
      </LegalSection>

      <LegalSection title="8. Données personnelles">
        <LegalParagraph>
          Les données collectées dans le cadre des commandes sont traitées conformément au Règlement Général sur la
          Protection des Données (RGPD). Pour en savoir plus sur le traitement de vos données et l&apos;exercice de
          vos droits, consultez notre politique de confidentialité.
        </LegalParagraph>
      </LegalSection>

      <LegalSection title="9. Droit applicable et litiges">
        <LegalParagraph>
          Les présentes CGU/CGV sont régies par le droit français. En cas de litige, une solution amiable sera
          recherchée en priorité avant toute action judiciaire. À défaut d&apos;accord, les tribunaux français
          compétents seront seuls habilités à connaître du différend.
        </LegalParagraph>
      </LegalSection>
    </LegalPage>
  );
}
