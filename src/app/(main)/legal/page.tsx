import { LegalPage, LegalSection, LegalParagraph, LegalList } from "@/components/legal-page";

export const metadata = { title: "Mentions légales · Pilote Shopping" };

export default function LegalNoticePage() {
  return (
    <LegalPage
      title="Mentions légales"
      subtitle="Informations relatives à l'éditeur, à l'hébergement et à la propriété intellectuelle du site Pilote Shopping."
      updatedAt="15 juin 2026"
    >
      <LegalSection title="1. Éditeur du site">
        <LegalParagraph>
          Le présent site est édité par <strong className="text-foreground">Pilote Shopping</strong>, marque
          exploitée par <strong className="text-foreground">PiloteCode</strong>, marketplace de produits
          numériques premium (licences, comptes et abonnements).
        </LegalParagraph>
        <LegalList
          items={[
            <>Dénomination : PiloteCode (Pilote Shopping)</>,
            <>Forme : entreprise individuelle</>,
            <>Siège social : 12 rue du Numérique, 67000 Strasbourg, France</>,
            <>Numéro SIRET : 000 000 000 00000 (à compléter)</>,
            <>
              Adresse e-mail :{" "}
              <a href="mailto:contact@piloteshopping.com" className="text-primary hover:underline">
                contact@piloteshopping.com
              </a>
            </>,
            <>Directeur de la publication : Ewan Beraud</>,
          ]}
        />
      </LegalSection>

      <LegalSection title="2. Hébergement">
        <LegalParagraph>
          Le site est hébergé par un prestataire d&apos;infrastructure cloud assurant la disponibilité et la
          sécurité des services :
        </LegalParagraph>
        <LegalList
          items={[
            <>Hébergeur : Vercel Inc.</>,
            <>Adresse : 340 S Lemon Ave #4133, Walnut, CA 91789, États-Unis</>,
            <>
              Site web :{" "}
              <a href="https://vercel.com" target="_blank" rel="noreferrer" className="text-primary hover:underline">
                vercel.com
              </a>
            </>,
          ]}
        />
      </LegalSection>

      <LegalSection title="3. Propriété intellectuelle">
        <LegalParagraph>
          L&apos;ensemble des éléments composant le site (structure, textes, logos, marques, visuels, interface et
          code) est protégé par le droit de la propriété intellectuelle et demeure la propriété exclusive de
          Pilote Shopping, sauf mention contraire.
        </LegalParagraph>
        <LegalParagraph>
          Toute reproduction, représentation, modification ou exploitation, totale ou partielle, sans autorisation
          écrite préalable est interdite et constitue une contrefaçon sanctionnée par les articles L.335-2 et
          suivants du Code de la propriété intellectuelle. Les marques tierces et noms de produits cités restent la
          propriété de leurs détenteurs respectifs.
        </LegalParagraph>
      </LegalSection>

      <LegalSection title="4. Responsabilité">
        <LegalParagraph>
          Pilote Shopping s&apos;efforce d&apos;assurer l&apos;exactitude des informations diffusées sur le site,
          sans toutefois garantir qu&apos;elles soient exemptes d&apos;erreurs ou d&apos;omissions. L&apos;éditeur
          ne saurait être tenu responsable d&apos;une indisponibilité temporaire du service ou d&apos;un usage non
          conforme du site par l&apos;utilisateur.
        </LegalParagraph>
      </LegalSection>

      <LegalSection title="5. Contact">
        <LegalParagraph>
          Pour toute question relative au site ou à ces mentions légales, vous pouvez nous écrire à l&apos;adresse{" "}
          <a href="mailto:contact@piloteshopping.com" className="text-primary hover:underline">
            contact@piloteshopping.com
          </a>{" "}
          ou rejoindre notre communauté sur Discord.
        </LegalParagraph>
      </LegalSection>
    </LegalPage>
  );
}
