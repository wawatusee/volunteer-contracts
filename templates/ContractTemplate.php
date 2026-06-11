<?php
/**
 * ContractTemplate
 *
 * Renders the bilingual (NL/EN) contract HTML from a variables map.
 *
 * Design decisions:
 *  - The bilingual text is embedded directly in PHP as a method rather than a
 *    separate .html file, so the class is self-contained and easy to deploy.
 *    To externalise it, change renderNl() / renderEn() to load files instead.
 *  - Variables are injected via simple {{key}} substitution.
 *  - Adding a new language requires only a new renderXx() method.
 *  - The render() method accepts a 'language' hint: 'nl', 'en', or 'both'.
 *
 * @package VolunteerContracts\Template
 */
class ContractTemplate
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Render the complete contract HTML.
     *
     * @param  array  $vars     Key→value map from Contract::toTemplateVars()
     * @param  string $language 'nl' | 'en' | 'both'
     * @return string           Full HTML fragment (no <html>/<body> wrapper)
     */
    public static function render(array $vars, string $language = 'both'): string
    {
        $vars = self::escapeVars($vars);

        $html = '<div class="contract-document">';

        if (in_array($language, ['both', 'nl'], true)) {
            $html .= '<div class="contract-column contract-nl">';
            $html .= self::fillTemplate(self::templateNl(), $vars);
            $html .= '</div>';
        }

        if (in_array($language, ['both', 'en'], true)) {
            $html .= '<div class="contract-column contract-en">';
            $html .= self::fillTemplate(self::templateEn(), $vars);
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    // -------------------------------------------------------------------------
    // Template substitution
    // -------------------------------------------------------------------------

    /**
     * Replace {{key}} placeholders in the template with escaped values.
     */
    private static function fillTemplate(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        // Remove any un-filled placeholders (optional fields left empty)
        return preg_replace('/\{\{[a-z_]+\}\}/', '', $template);
    }

    /**
     * HTML-escape all variable values.
     * Values come from Contract::toTemplateVars() which already calls
     * htmlspecialchars on storage, but we apply it again for safety.
     */
    private static function escapeVars(array $vars): array
    {
        return array_map(
            fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $vars
        );
    }

    // -------------------------------------------------------------------------
    // Dutch template
    // -------------------------------------------------------------------------

    private static function templateNl(): string
    {
        return <<<'HTML'
<h2 class="contract-title">OVEREENKOMST VOOR VRIJWILLIGERSWERK</h2>
<section class="contract-section">
    <p>Tussen de Partijen:</p>

<section>

<section class="contract-section">
    <h3>Artikel 1 – Partijen</h3>
    <p>
        Parapluie vzw, bedrijfsnummer 0458.091.012<br>
        geregistreerd kantoor op Henri Jasparlaan 122, 1060 Sint-Gillis<br>
        vertegenwoordigd door Eliah Debaene<br>
         hierna genoemd "De Organisator"
        
    </p>
    <p>en,</p>
    <p>
        Naam : {{volunteer_name}}<br>
        Geboortedatum : {{birth_date}}<br>
        Adres : {{address}}<br>
        e-mail: <strong>{{email}}</strong><br>
        IBAN: <strong>{{iban}}</strong><br>
        hierna "de Vrijwilliger" genoemd,<br>
    
    <strong>
        wordt het volgende overeengekomen:
</strong></p>
</section>

<section class="contract-section">
    <h3>Artikel 2 – Voorwerp van de overeenkomst</h3>
    <p>
        De vrijwilliger zal zich inzetten voor het evenement <strong>{{event_name}}</strong> (hierna “het evenement” genoemd) en diverse taken uitvoeren vóór, tijdens of na het evenement.
        Het werk gaat door van  <strong>{{start_date}}</strong> tot en met <strong>{{end_date}}</strong>.
    </p>
</section>

<section class="contract-section">
    <h3>Artikel 3 – Vergoeding</h3>
    <p>
        De Vrijwilliger ontvangt een vergoeding van maximaal €44,02 per dag voor het verrichtte werk, in overeenstemming met de wettelijke regels voor vrijwilligersvergoedingen. De totale vergoeding bedraagt <strong>€&nbsp;{{total_amount}}</strong>
        en zal uiterlijk op {{payment_date}}    
    </p>
    <p>
        worden overgemaakt naar het volgende rekeningnummer: <strong>{{iban}}</strong>
        Deze vergoeding is bedoeld als kostenvergoeding en niet als salaris. Deze overeenkomst vormt geen arbeidsovereenkomst. De Organisatie heeft geen verplichtingen inzake sociale zekerheid of belastingafdracht.    </p>
</section>

<section class="contract-section">
    <h3>Artikel 4 – Verplichtingen van de Vrijwilliger</h3>
    <p>De Vrijwilliger verbindt zich ertoe:</p>
    <ul>
        <li>de opgedragen taken naar beste vermogen uit te voeren;</li>
        <li>de interne regels en richtlijnen van de Organisatie na te leven;</li>
        <li>de vertrouwelijkheid te respecteren van alle informatie waartoe hij/zij toegang heeft;</li>
        <li>zich correct te gedragen tegenover deelnemers, medewerkers en derden.</li>
    </ul>
</section>

<section class="contract-section">
    <h3>Artikel 5 – Verplichtingen van de Organisatie</h3>
    <p>De Organisatie verbindt zich ertoe:</p>
    <ul>
        <li>de Vrijwilliger tijdig en volledig te informeren over zijn/haar taken;</li>
        <li>de nodige middelen ter beschikking te stellen;</li>
        <li>een aangepaste verzekering te voorzien voor de duur van de activiteit;</li>
        <li>de kostenvergoeding tijdig te betalen.</li>
    </ul>
</section>

<section class="contract-section">
    <h3>Artikel 6 – Aansprakelijkheid</h3>
    <p>
        De Organisatie is burgerrechtelijk aansprakelijk voor schade veroorzaakt
        door de Vrijwilliger aan derden in het kader van de uitvoering van deze overeenkomst,
        tenzij de Vrijwilliger bedrog of een zware fout heeft gepleegd.
    </p>
</section>

<section class="contract-section">
    <h3>Artikel 7 – Duur en beëindiging</h3>
    <p>
        Deze overeenkomst neemt een aanvang op <strong>{{start_date}}</strong>
        en eindigt van rechtswege op <strong>{{end_date}}</strong>.
        Elke partij kan de overeenkomst voortijdig beëindigen mits voorafgaande schriftelijke kennisgeving.
    </p>
</section>

<section class="contract-section">
    <h3>Artikel 8 – Toepasselijk recht</h3>
    <p>
        Deze overeenkomst wordt beheerst door het Belgisch recht.
        Bij geschillen zijn uitsluitend de rechtbanken van het gerechtelijk
        arrondissement van de maatschappelijke zetel van de Organisatie bevoegd.
    </p>
</section>
<section class="contract-section">
    <h3>Artikel 9 – Vrijwilligersverzekering</h3>
    <p>
       De organisatie bevestigt dat een wettelijke vrijwilligersverzekering onder polisnummer 518 597 159
    </p>
</section>

<section class="contract-section contract-signatures">
    <h3>Handtekeningen</h3>
    <p>Opgesteld te Brussel, op <strong>{{signature_date}}</strong> en ondertekend in tweevoud.</p>
    <div class="signature-grid">
        <div class="signature-block">
            <p><strong>Voor de Organisatie</strong></p>
            <p>{{organisation_rep}}</p>
            <div class="signature-line"></div>
            <p class="signature-label">Handtekening</p>
        </div>
        <div class="signature-block">
            <p><strong>De Vrijwilliger</strong></p>
            <p>{{volunteer_name}}</p>
            <div class="signature-line"></div>
            <p class="signature-label">Handtekening</p>
        </div>
    </div>
</section>
HTML;
    }

    // -------------------------------------------------------------------------
    // English template
    // -------------------------------------------------------------------------

    private static function templateEn(): string
    {
        return <<<'HTML'


<h2 class="contract-title">VOLUNTEER AGREEMENT</h2>
<section class="contract-section">
    <p>Between the Parties:</p>

</section>

<section class="contract-section">
    <h3>Article 1 – Parties</h3>
    <p>Parapluie vzw, business number 0458.091.012,<br> registered office at Henri Jasparlaan 122, 1060
        Sint-Gillis,<br> represented by Eliah Debaene,<br> hereinafter referred to as "The Organizer",
    </p>
    <p>and,</p>
    <p>
        Name: {{volunteer_name}}<br>
        Date of birth: {{birth_date}}<br>
        Address: {{address}}<br>
        Email address: {{email}}<br>
        IBAN: <strong>{{iban}}</strong><br>
        hereinafter referred to as "The Volunteer"<br>
        <strong>it is agreed as follows:</strong>
    </p>

</section>

<section class="contract-section">
    <h3>Article 2 – Object of the agreement</h3>
    <p>
        The volunteer will contribute to the event <strong>{{event_name}}</strong> (hereinafter referred to as “the Event”) by executing various tasks before, during, or after the Event.
        The volunteer will work from <strong>{{start_date}}</strong> until <strong>{{end_date}}</strong>.
    </p>
</section>

<section class="contract-section">
    <h3>Article 3 – Compensation</h3>
    <p>
        The Volunteer will receive compensation of up to €44,02 per day for their work, in accordance with the legal regulations for volunteer compensation. The total compensation amounts to <strong>€&nbsp;{{total_amount}}</strong>
        and will be paid no later than {{payment_date}}
    </p>
    <p>
        into the following account: <strong>{{iban}}</strong>
        This compensation is intended as reimbursement of expenses and not as a salary. This agreement does not constitute an employment contract. The Organizer has no obligations regarding social security or tax regulations.
    </p>
</section>

<section class="contract-section">
    <h3>Article 4 – Obligations of the Volunteer</h3>
    <p>The Volunteer undertakes to:</p>
    <ul>
        <li>perform assigned tasks to the best of their ability;</li>
        <li>comply with the Organisation's internal rules and guidelines;</li>
        <li>maintain the confidentiality of all information they have access to;</li>
        <li>behave appropriately towards participants, staff, and third parties.</li>
    </ul>
</section>

<section class="contract-section">
    <h3>Article 5 – Obligations of the Organisation</h3>
    <p>The Organisation undertakes to:</p>
    <ul>
        <li>inform the Volunteer in a timely and complete manner about their tasks;</li>
        <li>provide the necessary means to carry out those tasks;</li>
        <li>arrange appropriate insurance for the duration of the activity;</li>
        <li>pay the expense allowance in a timely manner.</li>
    </ul>
</section>

<section class="contract-section">
    <h3>Article 6 – Liability</h3>
    <p>
        The Organisation is civilly liable for damage caused by the Volunteer
        to third parties in the context of performing this agreement,
        unless the Volunteer has committed fraud or a serious fault.
    </p>
</section>

<section class="contract-section">
    <h3>Article 7 – Duration and Termination</h3>
    <p>
        This agreement commences on <strong>{{start_date}}</strong>
        and terminates by operation of law on <strong>{{end_date}}</strong>.
        Either party may terminate the agreement early upon prior written notice.
    </p>
</section>

<section class="contract-section">
    <h3>Article 8 – Governing Law</h3>
    <p>
        This agreement is governed by Belgian law.
        In the event of disputes, the courts of the judicial district
        of the Organisation's registered office shall have exclusive jurisdiction.
    </p>
</section>
<section class="contract-section">
    <h3>Artikel 9 – Vrijwilligersverzekering</h3>
    <p>
       The organiser confirms that a legal volunteer insurance policy has been subscribed to under the policy number 518 597 159
    </p>
</section>

<section class="contract-section contract-signatures">
    <h3>Signatures</h3>
    <p>This agreement is drawn up in Brussels, on <strong>{{signature_date}}</strong> and signed in duplicate.</p>
    <div class="signature-grid">
        <div class="signature-block">
            <p><strong>For Parapluie VZW</strong></p>
            <p>{{organisation_rep}}</p>
            <div class="signature-line"></div>
            <p class="signature-label">Signature</p>
        </div>
        <div class="signature-block">
            <p><strong>The Volunteer</strong></p>
            <p>{{volunteer_name}}</p>
            <div class="signature-line"></div>
            <p class="signature-label">Signature</p>
        </div>
    </div>
</section>
HTML;
    }
}
