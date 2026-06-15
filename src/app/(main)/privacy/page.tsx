import { LegalPage, LegalSection, LegalParagraph, LegalList } from "@/components/legal-page";

export const metadata = { title: "Politique de confidentialité · Pilote Shopping" };

export default function PrivacyPage() {
  return (
    <LegalPage
      title="Politique de confidentialité"
      subtitle="Comment Pilote Shopping collecte, utilise et protège vos données personnelles, dans le respect du RGPD."
      updatedAt="15 juin 2026"
    >
      <LegalSection title="1. Données collectées">
        <LegalParagraph>
          Dans le cadre de l&apos;utilisation de nos services, nous collectons uniquement les données nécessaires au
          bon fonctionnement de la plateforme :
        </LegalParagraph>
        <LegalList
          items={[
            <>Données d&apos;identification : nom, prénom, nom d&apos;utilisateur, adresse e-mail.</>,
            <>Données de compte : historique de commandes, solde du portefeuille, préférences.</>,
            <>Données de transaction : produits achetés, montants, dates (les données de carte ne sont jamais stockées par nos soins).</>,
            <>Données techniques : adresse IP, type de navigateur, journaux de connexion.</>,
          ]}
        />
      </LegalSection>

      <LegalSection title="2. Finalités du traitement">
        <LegalParagraph>Vos données sont traitées pour les finalités suivantes :</LegalParagraph>
        <LegalList
          items={[
            <>Gérer votre compte et exécuter vos commandes.</>,
            <>Assurer la livraison des produits numériques et le support client.</>,
            <>Prévenir la fraude et garantir la sécurité de la plateforme.</>,
            <>Respecter nos obligations légales et comptables.</>,
          ]}
        />
      </LegalSection>

      <LegalSection title="3. Base légale (RGPD)">
        <LegalParagraph>
          Conformément au Règlement (UE) 2016/679 (RGPD), chaque traitement repose sur une base légale :
        </LegalParagraph>
        <LegalList
          items={[
            <>L&apos;exécution du contrat de vente pour la gestion des commandes et du compte.</>,
            <>Le respect d&apos;obligations légales pour la facturation et la conservation comptable.</>,
            <>Notre intérêt légitime pour la sécurité et la prévention de la fraude.</>,
            <>Votre consentement pour les communications marketing facultatives.</>,
          ]}
        />
      </LegalSection>

      <LegalSection title="4. Durée de conservation">
        <LegalParagraph>
          Vos données sont conservées le temps strictement nécessaire aux finalités poursuivies : les données de
          compte sont conservées tant que celui-ci est actif, et les données de facturation sont archivées pendant la
          durée légale de conservation (jusqu&apos;à 10 ans). Au-delà, les données sont supprimées ou anonymisées.
        </LegalParagraph>
      </LegalSection>

      <LegalSection title="5. Vos droits">
        <LegalParagraph>
          Conformément à la réglementation, vous disposez à tout moment des droits suivants sur vos données :
        </LegalParagraph>
        <LegalList
          items={[
            <>Droit d&apos;accès et de portabilité de vos données.</>,
            <>Droit de rectification des informations inexactes.</>,
            <>Droit à l&apos;effacement (« droit à l&apos;oubli »).</>,
            <>Droit de limitation et d&apos;opposition au traitement.</>,
            <>Droit d&apos;introduire une réclamation auprès de la CNIL.</>,
          ]}
        />
        <LegalParagraph>
          Pour exercer ces droits, contactez-nous à l&apos;adresse{" "}
          <a href="mailto:privacy@piloteshopping.com" className="text-primary hover:underline">
            privacy@piloteshopping.com
          </a>
          . Une réponse vous sera apportée dans un délai maximum d&apos;un mois.
        </LegalParagraph>
      </LegalSection>

      <LegalSection title="6. Cookies">
        <LegalParagraph>
          Le site utilise des cookies strictement nécessaires à son fonctionnement (authentification, panier,
          préférences). Des cookies de mesure d&apos;audience peuvent être déposés sous réserve de votre
          consentement. Vous pouvez à tout moment configurer votre navigateur pour refuser tout ou partie des cookies.
        </LegalParagraph>
      </LegalSection>

      <LegalSection title="7. Contact du délégué à la protection des données (DPO)">
        <LegalParagraph>
          Pour toute question relative à la protection de vos données personnelles, vous pouvez contacter notre
          délégué à la protection des données à l&apos;adresse{" "}
          <a href="mailto:dpo@piloteshopping.com" className="text-primary hover:underline">
            dpo@piloteshopping.com
          </a>
          .
        </LegalParagraph>
      </LegalSection>
    </LegalPage>
  );
}
