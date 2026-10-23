# Organiser page for allevandreture.dk: build spec and content

Hand this file to Claude Code together with the design canvas (Main = mobile, Desktop = desktop, Kort = organiser name on event cards). The site runs on WordPress with Rank Math SEO. Copy is Danish. Build mobile first.

Items marked [FYLD UD] are facts I could not verify. Fill them in or delete the sentence. Never publish a bracket.

## 1. Goal

One indexable page per organiser that ranks for searches like "Dansk Vandrelaug vandreture", "Mammutmarch Aarhus" and "vandreture arrangør". The page must have enough unique text and structure that Google does not treat it as a thin filter result.

## 1b. Design tokens: use the existing theme.json only

The design files use hex values that are exactly the values in the site's theme.json. Do not add any new colors, fonts, font sizes or spacing values. Use the WordPress preset variables, for example `var(--wp--preset--color--forest)`, `var(--wp--preset--font-size--small)` and `var(--wp--preset--spacing--large)`.

Colors (hex in the design file, then the theme slug):

* #2D5F3F forest: header and footer background, primary buttons, region chips, badge text, outline button border and text, labels above filters
* #C2D9B0 meadow: all borders and dividers, "Officiel arrangør" badge background, km chip background, image placeholder
* #FAF5EC cream: filter box, stat tiles, facts box, price chip background, logo circle background
* #7A5230 bark: walk dates on cards (amber failed contrast on white, #96), text in the price chip ("Gratis", "50 kr")
* #7A7268 pebble: secondary text on white (breadcrumb, place, counts, "Kilde")
* #1D1A14 charcoal: body text, headings, text on cream backgrounds
* #FFFFFF white: page and card background, text on forest
* #1E6FA5 fjord: all text links (the theme's link color)
* #154F78 fjord-deep: link hover, active and focus

Fonts (self hosted in the theme, do not load Google Fonts):

* Newsreader weight 300 only: headings, card titles, big numbers, logo text
* Inter weight 400 and 600 only: everything else. The design uses no weight 500, because it is not loaded.

Font sizes (theme slug, size):

* x-small 12px: date line, chips, labels, badge
* small 14px: breadcrumb, place line, filter chips, counts, source line
* regular 16px: body text, buttons, list rows
* medium 18px: intro text on desktop
* large 20px: card titles
* 2-extra-large 32px (fluid 24 to 32): section headings on mobile, stat numbers
* 3-extra-large 40px (fluid 28 to 40): logo initials, stat numbers on desktop
* 4-extra-large 48px (fluid 32 to 48): section headings on desktop
* 5-extra-large 56px (fluid 36 to 56): H1 on desktop (36px on mobile)

The h1 and h2 elements already get these sizes from theme.json, so do not set them again in CSS.

Spacing and layout:

* Page gutters are the theme's root padding (spacing "large", 16 to 32px)
* Gap between sections is spacing "extra-large" (32px on mobile, up to 64px on desktop)
* Wide content is limited to 1376px (wideSize). Long text such as the FAQ is limited to 800px (contentSize).
* Body line height is 1.5 and heading line height is 1.2, both from the theme

Things the theme.json does not define (take these from the existing front page CSS, and match them exactly):

* Border radius: the design uses 12px on cards, filter box, stat tiles and facts box, 8px on inputs, and fully round on chips and buttons. Check the front page and copy its values.
* Header and footer: the green bars in the design are placeholders. Use the existing header and footer template parts.
* Card shadows and hover states: none in the design. Copy from the front page cards.

Accessibility notes on the existing colors:

* Amber (#D97535) on white is about 3.2 to 1, below the 4.5 to 1 needed for 12px text. The front page already uses it for dates. If you want to fix it, use bark (#7A5230) for the date text.
* Pebble (#7A7268) on white is about 4.7 to 1 and passes. On cream it is about 4.4 to 1 and fails slightly, so use charcoal for small text on cream backgrounds (the design already does).

## 2. Page order (top to bottom)

1. Header and breadcrumb (Kalender / Arrangører / Navn)
2. Hero: logo (initials fallback), badge "Officiel arrangør" (only when flagged), H1 with the organiser name, short description, button "Besøg hjemmeside" (external link, rel="noopener", no nofollow needed for an editorial link, use rel="nofollow noopener" if the link is user supplied)
3. "Arrangøren i tal": four derived stats and "Næste tur"
4. "Kommende ture": filters and walk cards, same component as the front page
5. "Om [Navn]": 150 to 250 words plus a facts list and a source line
6. "Ture i hele landet": regions with counts, each linking to the calendar filtered by region
7. "Tidligere ture": last 4 to 8 past walks, link to full archive
8. "Ofte stillede spørgsmål": native details and summary elements, rendered in the HTML so Google can read them
9. "Andre arrangører": 6 related organisers, ordered by shared regions
10. Footer

## 3. URL and routing

* Pattern: /arrangoerer/{slug}/ for example /arrangoerer/dansk-vandrelaug/
* Index page: /arrangoerer/ lists all organisers (also the target of "Se alle arrangører")
* Slugs are ASCII (oe, ae, aa for æ, ø, å) and never change once published. If one changes, 301 from the old URL.
* Suggested WordPress setup: custom post type "arrangoer" with archive at /arrangoerer/. Walks reference the organiser by post ID, not by name string. (Built: post type `organiser` for the page, but walks reference the `event_organiser` term, and the post links to the term; see the note under section 4.)
* Past walks keep their own URLs and stay indexable.

## 4. Data model

> **Changed in the build:** an organiser's page is a post (post type `organiser`) linked to its `event_organiser` term, edited in the block editor from locked section patterns, not the admin fields below. The facts, source line and generated summary are dropped. See [the plan's step 5](../../plans/organisers-plan.md#step-5-organiser-pages) and [decision 0008](../../decisions/0008-organiser-pages-as-post-type.md).

Stored per organiser (editable in WP admin):

* name, slug
* short_description (1 to 2 sentences, shown in hero and used in meta fallback)
* about_text (150 to 250 words, rich text, 2 to 4 paragraphs)
* logo (square, min 256 px), logo_alt
* website_url
* is_official (boolean, controls the badge)
* facts (repeater of label and value, optional rows: Afdelinger, Medlemskab, Kontakt, Adresse, Grundlagt, Tilmelding)
* source_url and source_checked_at (shows "Kilde: dvl.dk, hentet 19. oktober 2026")
* faq (repeater of question and answer, 3 to 6 items)

Derived at render time from the walks (never typed by hand):

* upcoming_count, region_count, regions with counts
* min_km and max_km
* free_count (walks with price 0)
* next_walk (title, date, city)
* past walks (latest 8)

Auto paragraph, rendered at the end of the "Om" text so every page has unique, current text even when about_text is short:

"{Navn} har {upcoming_count} kommende vandreture i {regions}. Turene er fra {min_km} til {max_km} km, og {free_count} af dem er gratis. Næste tur er {next_title} den {next_date} ved {next_city}."

Handle singular and plural ("1 tur", "2 ture"). Omit the sentence parts that have no data.

## 5. Meta, headings and copy rules

* Title template: `{Navn}: {N} kommende vandreture | Alle Vandreture` (aim for 60 characters or fewer, drop "{N} kommende" if too long)
* Meta description template: `Se kommende vandreture fra {Navn}: {N} ture i {R} regioner, {F} gratis. Find dato, sted og længde, og tilmeld dig hos arrangøren.` (aim for 155 characters or fewer)
* Zero upcoming walks: `Se tidligere vandreture fra {Navn} og få besked om nye ture. Find dato, sted og længde på Alle Vandreture.`
* One H1 (organiser name). H2 for each section as in the design. Do not skip heading levels.
* Open Graph: og:title and og:description same as above, og:image is the logo on a plain background (1200 by 630 if possible), og:type website.
* Write the about text yourself. Do not copy text from the organiser's website word for word.
* Show "Kilde" and a check date so visitors and Google see that the facts have an origin.

## 6. Indexing rules

* Index the page when it has about_text and at least one upcoming or past walk.
* An organiser with no upcoming walks stays indexable (the archive and about text carry it). Show the zero state: "Der er ingen kommende ture lige nu. Se tidligere ture herunder."
* noindex, follow only when there is no about_text and no walks at all.
* Canonical points to the clean URL. Filter and paging parameters (?region=, ?laengde=, ?side=) canonicalise to the clean URL and are not in the sitemap.
* Include /arrangoerer/ and every organiser page in the XML sitemap (Rank Math handles this once the post type is public).
* Breadcrumb visible in the page and as BreadcrumbList.

## 7. Structured data (JSON-LD)

Use Rank Math's schema where it fits and extend it with the `rank_math/json_ld` filter. Output these on the organiser page:

1. Organization: name, url (the organiser page), sameAs [website_url], logo, description (short_description), email and telephone when public, address when public
2. BreadcrumbList: Kalender, Arrangører, Navn
3. ItemList of up to 12 upcoming walks. Each item is an Event with: name, startDate (ISO 8601 with Europe/Copenhagen offset), eventAttendanceMode OfflineEventAttendanceMode, eventStatus EventScheduled, location (Place with name and address, geo if known), organizer (the Organization above), image, offers (price, priceCurrency DKK, url to the registration link, availability) or isAccessibleForFree true for free walks, url (the walk page)
4. Do not add FAQPage markup expecting rich results. Google shows FAQ rich results for very few sites now. The visible FAQ is still useful to visitors, so keep it in the page.

Validate with the Rich Results Test and fix warnings about missing offers or images.

## 8. Event card change (see the Kort board)

* Add an organiser line under the walk title: round logo (24 px, initials fallback) plus the organiser name, linking to the organiser page. Variant B in the design.
* The whole card still links to the walk. Use the stretched link pattern: the title link covers the card with a pseudo element, and the organiser link sits above it with position relative and a higher z-index, so there is no link inside a link.
* The organiser link needs a touch area of at least 32 px height on mobile (44 px preferred when space allows).
* On the walk's own page, add an "Arrangør" row to the facts box between "Sted" and the booking button. Hide it when the walk has no organiser.
* Front page: make the names in the "Officielle arrangører" strip links to the organiser pages.
* Region pages and the front page should link to organisers wherever an organiser name is shown.

## 9. Accessibility and performance

* Real buttons and links only. Native details and summary for the FAQ.
* Text contrast at least 4.5 to 1 (the design colours were chosen for this).
* Logo and walk images: width and height set, loading="lazy" below the fold, first walk image not lazy if it is above the fold on desktop.
* Filters work without a full reload and the result count is announced (aria-live polite).
* The page is fully readable without JavaScript: the walk cards and about text are in the server HTML.

## 10. Content: the seven organisers on the front page today

Source of the list: the "Officielle arrangører" strip on allevandreture.dk. Texts below are drafts for you to correct.

### 10.1 DVL (Dansk Vandrelaug), slug dansk-vandrelaug

Verified on dvl.dk (19 October 2026): tagline "Danmarks største vandrefællesskab", 20 local departments (including Aarhus, København, Viborg), about 3,000 walks a year according to one page and about 2,200 according to another (the draft says "over 2.000" to be safe), walk types Vandring Plus, Lang lørdag, Gå grønt gennem byen, vandreferier, membership 147,50 kr for the first half year then 295 kr per half year, address Kultorvet 7, 1175 København K, phone 3312 1165 (Tuesday and Friday 9 to 12), email dvl@dvl.dk.

* Short description: Dansk Vandrelaug er en landsdækkende forening med lokale afdelinger, der arrangerer gåture året rundt.
* Title: Dansk Vandrelaug: 12 kommende vandreture | Alle Vandreture
* Meta: Se kommende vandreture fra Dansk Vandrelaug: 12 ture i 4 regioner, 9 gratis. Find dato, sted og længde, og tilmeld dig hos arrangøren.
* About text: see the design, sections "Om Dansk Vandrelaug" (three paragraphs, already written there)
* Facts: Afdelinger 20; Medlemskab (price above); Kontakt (email and phone); Adresse
* FAQ:
  * Hvad er Dansk Vandrelaug? Dansk Vandrelaug er en forening, der kalder sig Danmarks største vandrefællesskab. Lokale afdelinger arrangerer gåture i hele landet.
  * Er turene gratis? Prisen står på hver tur. Mange af turene her på siden er gratis at deltage i.
  * Skal jeg være medlem for at deltage? [FYLD UD: er turene åbne for ikke medlemmer?]
  * Hvordan tilmelder jeg mig en tur? Åbn turen og følg linket til arrangørens egen side. Nogle ture kræver ikke tilmelding.
  * Hvor finder jeg turene tættest på mig? Brug regionsfilteret øverst på siden eller vælg en region i listen Ture i hele landet.

### 10.2 Mammutmarch, slug mammutmarch

Verified on mammutmarch.dk (19 October 2026): motto "100 KM. 24 Timer. Til Fods.", distances 30, 42, 55, 75 and 100 km, events listed in Berlin, Aarhus, Stockholm, København and Göteborg with dates from November 2026 to August 2027. Not found on the site: who runs it in Denmark, founding year, price, charity link, time limits per distance. A fundraising page for a "Hafnia Mammutmarch" on 2 May 2026 exists on indsamling.boernecancerfonden.dk, which suggests walkers raise money for charity at some events. That is a lead to check, not a fact.

* Short description: Mammutmarch er en lang vandrebegivenhed, hvor du vælger en distance mellem 30 og 100 km og går den sammen med andre.
* Title: Mammutmarch: {N} kommende vandreture | Alle Vandreture
* Meta: Se kommende Mammutmarch i Danmark: lange gåture på 30 til 100 km. Find dato, by og distance, og tilmeld dig hos arrangøren.
* About text:
  Mammutmarch er en begivenhed for dig, der vil prøve en rigtig lang gåtur. Under mottoet "100 KM. 24 Timer. Til Fods." vælger du selv en distance på 30, 42, 55, 75 eller 100 km og går den sammen med andre deltagere.
  Begivenhederne holdes i flere byer. I Danmark er det blandt andre Aarhus og København, og der er også arrangementer i Berlin, Stockholm og Göteborg. Datoerne ligger spredt fra november 2026 til august 2027.
  [FYLD UD: hvem der står bag i Danmark, tidsgrænse for hver distance, hvad der er med i prisen, om der er velgørenhed.]
  På Alle Vandreture samler vi Mammutmarchs danske ture. Tid, sted og pris kan ændre sig, så tjek altid arrangørens egen side.
* Facts: Distancer 30, 42, 55, 75 og 100 km; Byer i Danmark Aarhus og København; Tilmelding [FYLD UD]; Pris [FYLD UD]
* FAQ:
  * Hvor lang er en Mammutmarch? Du vælger selv mellem 30, 42, 55, 75 og 100 km.
  * Hvor holdes den i Danmark? Arrangementer er listet i blandt andet Aarhus og København. Se den enkelte tur for præcist startsted.
  * Skal jeg være i god form? [FYLD UD: arrangørens egne råd]
  * Hvordan tilmelder jeg mig? Følg linket på turens side til arrangørens tilmelding.

### 10.3 Over Stok og Sten, slug over-stok-og-sten

Verified on a Trainerday listing (19 October 2026): "Over Stok og Sten 24 timer 2026", Saturday 21 November 2026, Ejbyhallen in Ejby. Three marked nature loops of 5,5 km, each with 94 m of climbing, can be walked in any order. Nine loops are 50 km and eighteen loops are 100 km. Forest and bog, grass paths, gravel, dirt roads, cycle paths, quiet roads and some asphalt. Walkers return indoors after each loop and can rest and change shoes. Registration through sportstiming.dk. Search results also show events under the same name on Fanø and in Silkeborg. The organiser is not named on the listing.

* Short description: Over Stok og Sten arrangerer vandreudfordringer i naturen, blandt andet 24 timers gåture på markerede runder.
* Title: Over Stok og Sten: {N} kommende vandreture | Alle Vandreture
* Meta: Se kommende ture fra Over Stok og Sten, blandt andet 24 timers gåtur i Ejby. Find dato, sted og længde, og tilmeld dig via Sportstiming.
* About text:
  Over Stok og Sten arrangerer vandreudfordringer i naturen. Et eksempel er Over Stok og Sten 24 timer, som holdes lørdag 21. november 2026 ved Ejbyhallen i Ejby.
  Her går du tre markerede naturløjper på 5,5 km, hver med omkring 94 højdemeter, i den rækkefølge du selv vil. Ruterne går over skovstier, mose, grusveje og stille veje, og du kommer indendørs mellem runderne, så du kan hvile og skifte sko. Ni runder giver 50 km, og atten runder giver 100 km.
  [FYLD UD: hvem der står bag, hvilke andre steder og datoer I arrangerer (Silkeborg, Fanø), og hvad der er med i prisen.]
  Tilmelding sker via Sportstiming. Tjek altid arrangørens side, før du tilmelder dig.
* Facts: Næste større begivenhed 24 timer, 21. nov 2026, Ejbyhallen; Runde 5,5 km, 94 højdemeter; Tilmelding Sportstiming
* FAQ:
  * Hvad er Over Stok og Sten 24 timer? En gåbegivenhed på 24 timer, hvor du går markerede runder i naturen og vælger selv, hvor mange du når.
  * Hvor langt kan jeg gå? Hver runde er 5,5 km. Ni runder er 50 km og atten runder er 100 km. Du kan også gå en anden samlet distance.
  * Kan jeg hvile undervejs? Ja, du kommer indendørs efter hver runde og kan hvile og skifte sko.
  * Hvordan tilmelder jeg mig? Via Sportstiming. Følg linket på turens side.

### 10.4 The Long Lonely Wolf Walk, slug the-long-lonely-wolf-walk

Nothing verified. Searches only returned general articles about wolves in Denmark and long distance trails. Ask the organiser, or check the walk pages already in your calendar.

* Short description: [FYLD UD: én til to sætninger om, hvad The Long Lonely Wolf Walk er]
* Title and meta: use the templates in section 5
* About text: fill in these four things, then write 2 or 3 paragraphs: hvem står bag, hvor går turene, hvor lange er de, hvorfor navnet. Keep the auto paragraph from section 4 at the end.
* FAQ: Hvor går turen? [FYLD UD]. Hvor lang er den? [FYLD UD, tag fra turene]. Hvordan tilmelder jeg mig? Følg linket på turens side til arrangørens egen side.

### 10.5 Amager Rundt, slug amager-rundt

Nothing verified about the organiser. Context you may use after checking: Amager has a pilgrim route called Amarminoen (see lex.dk/Amarminoen), but do not link it to this organiser unless it is true.

* Short description: [FYLD UD: én til to sætninger om, hvad Amager Rundt er]
* About text: fill in: hvem står bag, hvor starter og slutter turen, hvor lang er runden, hvornår på året. Keep the auto paragraph at the end.
* FAQ: Hvor starter turen? [FYLD UD, tag fra turene]. Hvor lang er runden? [FYLD UD]. Hvordan kommer jeg dertil? [FYLD UD: nærmeste station]. Hvordan tilmelder jeg mig? Følg linket på turens side.

### 10.6 En GÅtur langs kysten, slug en-gatur-langs-kysten

Nothing verified. Searches returned other coastal routes, not this organiser.

* Short description: [FYLD UD: én til to sætninger om, hvad En GÅtur langs kysten er]
* About text: fill in: hvem står bag, hvilken kyststrækning, hvor lange er etaperne, hvordan hænger turene sammen (en serie eller enkeltture). Keep the auto paragraph at the end.
* FAQ: Hvilken kyststrækning går I? [FYLD UD]. Skal jeg gå alle etaper? [FYLD UD]. Hvordan tilmelder jeg mig? Følg linket på turens side.

### 10.7 Opdag Verden, slug opdag-verden

Nothing verified. The name is generic and search returned unrelated travel operators, so make sure not to mix them up with this organiser.

* Short description: [FYLD UD: én til to sætninger om, hvad Opdag Verden er]
* About text: fill in: hvem står bag, hvilken slags ture (by, natur, tema), hvor, hvor ofte. Keep the auto paragraph at the end.
* FAQ: Hvilke ture arrangerer I? [FYLD UD]. Er turene gratis? Prisen står på hver tur. Hvordan tilmelder jeg mig? Følg linket på turens side.

## 11. Open questions for you

1. Who edits the about text and FAQ, and where? (WP admin fields are assumed above.)
2. Do you have logos for each organiser and permission to show them?
3. Do you want an /arrangoerer/ index page in the first release, or only the seven single pages?
4. Should "Officiel arrangør" be set by you per organiser, or only for associations?

## 12. Definition of done

* Seven organiser pages render with the sections above, mobile first, tested at 360, 390 and 1280 px.
* No bracketed [FYLD UD] text is visible on any live page.
* Title, meta description, canonical, breadcrumb and JSON-LD validate.
* Event cards show the organiser line and link to the organiser page without nested links.
* Pages are in the sitemap and the front page organiser names link to them.
