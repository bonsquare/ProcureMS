{{--
    Procurement documents only (PR, RFQ, Abstract, NOA, PO, NTP, IAR, RIS, IARS, ICS and the reconciliation sheet).
    Owner request (2026-10-08): slightly heavier borders, and the filled-in data (school and header details, supplier, numbers, dates,
    purpose, names) in bold so it stands out from the printed labels. Item lines are NOT bold. See docs/specs.md 1.2 and 1.3.
    Labels stay in the weight the official form gives them; only data cells are listed here.
--}}
<style id="procurement-emphasis">
    /* School and header details (region, division, district) */
    .official-header > div:not([class]),
    .noa-letterhead > div:not([class]):not(:first-of-type),
    .ntp-letterhead > div:not([class]):not(:first-of-type),
    .letterhead > div:not([class]):not(:first-of-type) { font-weight: 700 !important; }

    /* Purchase Request: entity, department, PR no., dates, codes, purpose */
    .metadata .meta-value, .purpose > span:last-child { font-weight: 700 !important; }

    /* Request for Quotation: supplier fields, RFQ number and date */
    .rfq-fields .line, .rfq-page .u { font-weight: 700 !important; }

    /* Notice of Award / Notice to Proceed: addressee details */
    .noa-recipient .supplier-name, .noa-recipient .supplier-address, .ntp-recipient .supplier-name, .ntp-recipient .supplier-address { font-weight: 700 !important; }

    /* Purchase Order: header details (not the item table) */
    .po-table td.label + td, .po-account-lines .amount-value { font-weight: 700 !important; }

    /* Inspection and Acceptance Report: entity, supplier, numbers, dates (not the item rows) */
    .iar-topline > div:nth-child(2), .iar-topline .fund-value, .iar-table .details-row td:not(.label), .iar-table .date-row td:not(.center) { font-weight: 700 !important; }

    /* Requisition and Issuance Slip: entity, division, office, codes, purpose */
    .ris-wrap [style*="text-decoration:underline"], .ris-purpose td:last-child { font-weight: 700 !important; }

    /* Inventory Custodian Slip: entity, fund cluster, numbers. Item descriptions stay regular, not bold. */
    .ics-page td[style*="border-bottom"] { font-weight: 700 !important; }
    .ics-page .description strong { font-weight: 400 !important; }

    /* Generic document: the details table above the items */
    .generic-page > .doc-table:first-of-type td:not(.bold) { font-weight: 700 !important; }

    /* Abstract of Quotations: reference number and date */
    .abstract-meta .right { font-weight: 700 !important; }
</style>
