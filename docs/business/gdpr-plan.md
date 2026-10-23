# Alle Vandreture GDPR Plan

2026-09-18 · Petya

## Summary

Alle Vandreture needs no cookie banner, but it does need the basic GDPR setup: a privacy policy page, accepted processor agreements, a working user deletion, and a retention rule.

No cookie banner: the site sets no cookies for a visitor who is not logged in (verified 18 Sept 2026). The login cookies, the `pending_join` cookie and the Polylang language cookie are all "strictly necessary" under the Danish cookie rules (cookiebekendtgørelsen, enforced by Erhvervsstyrelsen), so they need no consent. This only holds as long as no analytics, embedded Google Map, YouTube embed or externally loaded Google Fonts are added later. Adding any of those means a consent banner (Complianz is the easy WordPress choice).

GDPR still applies because the site stores personal data: organiser accounts, attendee rows, contact form submissions and organiser contact details on events. The site is run by Petya as a private person, not a company, and GDPR applies the same way. The data controller is Petya, contact kontakt@allevandreture.dk. Datatilsynet is the authority.

What is not needed: no DPO, no registration with Datatilsynet, no cookie policy page, no consent checkbox on signup (a link to the privacy policy next to the signup button is enough).

Rough effort: about one hour for Petya's tasks, about half a day for Claude Code's tasks.

## What personal data the site handles

Five kinds of personal data, all low risk. "Legal basis" is the GDPR term for the reason you are allowed to process the data; every row needs one.

| Data | Where it lives | Why | Legal basis | Keep for |
| --- | --- | --- | --- | --- |
| Organiser account: name, email, Google account ID, login times | `wp_users`, `wp_usermeta` | To let organisers post and manage events | Contract (they asked for an account) | Until the user deletes the account |
| Attendee row: WP user ID, event ID, timestamp | `wp_event_attendees` | To show who joins and notify the organiser | Contract (they clicked "Jeg kommer") | 6 months after the event date |
| Organiser contact on events: name, email, URL | `event_organiser_name`, `event_organiser_email`, `event_organiser_url` post meta | To let participants reach the organiser and to send join notifications | Legitimate interest (published by the organiser for this purpose) | As long as the event is published |
| Scraped organiser details from public sites | Same meta fields, source in `event_source_name` | Same as above | Legitimate interest (public, published for the same purpose) | As long as the event is published, removed on request |
| Contact form: name, email, message | Fluent Forms entries table, plus the email to kontakt@ | To answer the message | Legitimate interest | 12 months |

Server logs on Nordicway (IP address, requested pages) are also personal data, but Nordicway keeps them as part of hosting and they are covered by the processor agreement.

Who receives the data ("processors", companies that handle data on your behalf): Nordicway (hosting, database, email for kontakt@), Google (login via Google OAuth), and the SMTP provider behind FluentSMTP if it is not Nordicway. Google login also means Google is a separate controller for what happens inside the Google account, which the privacy policy states.

## Tasks for Petya

These are things only you can do because they need your logins. Order does not matter much, but do task 1 first so the Google login does not get suspended.

1. Google OAuth consent screen. Go to Google Cloud Console, APIs & Services, OAuth consent screen, and set the privacy policy URL to `https://allevandreture.dk/privatliv` and the homepage to the new domain. Google checks this URL exists, so publish the page (Claude Code task 1) before or on the same day. If the app is still in "Testing" mode, this is also where you eventually publish it.
2. Nordicway processor agreement (databehandleraftale). Log in to Nordicway's customer panel and look for "Databehandleraftale" or "GDPR". Most Danish hosts have a standard agreement you accept with one click. Save a PDF copy in the project folder. If you cannot find it, email their support and ask for it; they are obliged to provide one.
3. Google processor terms. For Google login you are covered by Google's standard terms, but for your own record note in the fortegnelse that you rely on Google's Data Processing Terms and the EU-US Data Privacy Framework (Google LLC is certified). Nothing to sign.
4. FluentSMTP provider. Check which SMTP server FluentSMTP sends through. If it is Nordicway's mail server, task 2 covers it. If it is another service (Brevo, Mailgun, etc.), accept that service's data processing agreement in its dashboard.
5. Fluent Forms retention. In wp-admin, Fluent Forms, Settings, look for the option to not store entries in the database, or set a reminder to delete entries older than 12 months. The simplest is to turn off storing entries, since the message also arrives at kontakt@ by email.
6. Read the Danish privacy policy draft below, fix anything that is wrong about how the site actually works, and give it to Claude Code as the page content.
7. Save the fortegnelse section below as a file in the project folder. It is the one document Datatilsynet may ask for.
8. Set a yearly reminder to re-read the privacy policy when features change. The next known trigger is organisation members and event claiming ([#86](https://github.com/petyaferreira/vandrekalender-wp/issues/86)), which adds new data.

## Tasks for Claude Code

Paste this section as the brief. Staging first, as always. The plain language explanation after each task is for Petya.

1. Privacy policy page. Create a page with slug `privatliv` (Danish) using the text in the section below. Add it to the footer navigation next to `/om` and `/vilkaar`. No noindex needed; a privacy page may be indexed.
2. Signup and login notice. On the registration form and on the Google login button screen, add one line under the button: `Ved at oprette en konto accepterer du vores [vilkår](/vilkaar) og [privatlivspolitik](/privatliv).` This is the GDPR "information at collection" requirement. No checkbox.
3. Contact form notice. In the Fluent Forms contact form, add a short text under the message field: `Vi bruger dine oplysninger kun til at besvare din henvendelse. Læs vores [privatlivspolitik](/privatliv).`
4. Attendance notice. Next to the "Jeg kommer" button, a small line: `Arrangøren får besked om, at du deltager.` Participants must know their name goes to the organiser.
5. User deletion covers the attendee table. Hook into `deleted_user` (and `wpmu_delete_user` for later multisite) and delete all rows in `wp_event_attendees` where `user_id` matches. Also hook into WordPress's built in privacy tools: register an eraser with `wp_privacy_personal_data_erasers` that removes those rows, and an exporter with `wp_privacy_personal_data_exporters` that lists the events the user joined. In plain words: WordPress has a "delete user" screen and a "erase personal data" screen under Tools, and neither knows about your custom table unless you tell it.
6. Self service account deletion. On the organiser dashboard, add a "Slet min konto" link that goes to a confirmation page and calls `wp_delete_user()` with events reassigned to the `vandrekalender-bot` user (so published events survive) or trashed, Petya decides which. GDPR does not strictly require self service, an email to kontakt@ is enough, but it saves manual work.
7. Attendee retention cleanup. A daily WP-Cron job (`wp_schedule_event`) that deletes rows in `wp_event_attendees` whose event date is more than 6 months in the past. Log the count deleted to the error log. For recurring walks use the occurrence date, not the series.
8. Fluent Forms entries. Only if Petya keeps storing entries: a monthly WP-Cron job that deletes Fluent Forms entries older than 12 months via the plugin's API. Skip if entry storage is turned off.
9. Cookie hygiene check. Confirm the `pending_join` cookie has a short expiry (1 hour is enough), `HttpOnly`, `Secure`, `SameSite=Lax`, and is cleared in the login success handler on both the Google and native paths. Confirm nothing else sets a cookie for logged out visitors, including Polylang (its cookie can be disabled in Polylang settings if language detection is not needed).
10. Organiser removal request handling. Add an admin action (WP-CLI command or admin page button) that, given an email address, blanks `event_organiser_email` and `event_organiser_name` on all events with that email and lists them. This is for when a scraped organiser asks to be removed.

Acceptance test on staging: create a test user, join an event, delete the user in wp-admin, confirm the `wp_event_attendees` row is gone; run Tools, Erase Personal Data for another test user and confirm the same; load the site logged out in a private window and confirm no cookies.

## Privatlivspolitik (draft for /privatliv)

Plain Danish, written for a small site run by one person. Everything in square brackets is for Petya to confirm or fill in.

---

**Privatlivspolitik for Alle Vandreture**

Sidst opdateret: \[dato\]

**Hvem er ansvarlig**

Alle Vandreture (allevandreture.dk) drives af Petya Naydenova som privatperson. Har du spørgsmål om dine oplysninger, så skriv til kontakt@allevandreture.dk.

**Hvilke oplysninger vi behandler, og hvorfor**

*Besøgende uden konto.* Du kan bruge kalenderen og kortet uden at oprette en konto. Vi sætter ingen cookies og indsamler ingen oplysninger om dig, ud over de tekniske serverlogs (IP-adresse og besøgte sider), som vores hostingleverandør gemmer af sikkerhedshensyn.

*Arrangørkonto.* Opretter du en konto for at oprette vandreture, gemmer vi dit navn, din e-mail og tidspunkt for login. Logger du ind med Google, modtager vi dit navn, din e-mail og et Google-bruger-id fra Google. Vi bruger oplysningerne til at give dig adgang til din konto og dine ture. Grundlaget er aftalen med dig om at bruge tjenesten.

*Kontaktoplysninger på ture.* Når du opretter en tur, kan du angive arrangørnavn, e-mail og hjemmeside. De vises på turen, så deltagere kan kontakte dig, og e-mailen bruges til at give dig besked, når nogen tilmelder sig.

*Deltagelse.* Klikker du "Jeg kommer" på en tur, gemmer vi, at din konto deltager i turen, og arrangøren får besked med dit navn. Vi sletter oplysningen 6 måneder efter turen er afholdt.

*Ture fra andre hjemmesider.* En del ture i kalenderen er hentet automatisk fra arrangørernes egne offentlige hjemmesider. Her viser vi arrangørens navn og de kontaktoplysninger, arrangøren selv har offentliggjort, med link til kilden. Grundlaget er vores legitime interesse i at samle vandreture i Danmark ét sted. Er du arrangør og ønsker dine oplysninger fjernet, så skriv til os, og vi fjerner dem.

*Kontaktformular.* Skriver du til os via kontaktformularen, gemmer vi dit navn, din e-mail og din besked, indtil vi har svaret, og højst i 12 måneder.

**Cookies**

Vi sætter kun cookies, der er nødvendige for, at siden virker: en login-cookie, når du logger ind, en kortvarig cookie, der husker hvilken tur du var ved at tilmelde dig, mens du logger ind, og \[en cookie der husker dit sprogvalg\]. Vi bruger ingen statistik- eller markedsføringscookies, og derfor beder vi ikke om samtykke til cookies.

**Hvem vi deler oplysninger med**

Vi sælger ikke dine oplysninger. Vi bruger følgende leverandører, som behandler oplysninger på vores vegne: Nordicway (hosting og e-mail, Danmark) og Google (login med Google, USA, omfattet af EU-U.S. Data Privacy Framework). \[SMTP-leverandør, hvis anden end Nordicway.\] Logger du ind med Google, gælder Googles egen privatlivspolitik for din Google-konto.

**Hvor længe vi gemmer oplysninger**

Kontooplysninger gemmes, indtil du sletter din konto. Deltagelser slettes 6 måneder efter turen. Henvendelser slettes efter 12 måneder. Kontaktoplysninger på ture vises, så længe turen er offentliggjort.

**Dine rettigheder**

Du har ret til at få indsigt i, rette, slette og få udleveret de oplysninger, vi har om dig, og til at gøre indsigelse mod behandlingen. Du kan selv slette din konto under \[Min konto\], eller skrive til kontakt@allevandreture.dk. Vi svarer inden for en måned. Du kan klage til Datatilsynet, datatilsynet.dk.

**Ændringer**

Ændrer vi denne politik, opdaterer vi datoen øverst. Ved væsentlige ændringer giver vi besked til brugere med en konto pr. e-mail.

---

Note: the address of the data controller is normally stated. For a private person a contact email is accepted in practice, but if Petya prefers, a city ("København") can be added after the name.

## Fortegnelse over behandlingsaktiviteter

GDPR article 30 asks every controller to keep a record of what they process. For a site this size it is one table. Keep it as a file, update it when features change.

**Dataansvarlig:** Petya Naydenova, privatperson, kontakt@allevandreture.dk

**Tjeneste:** allevandreture.dk, kalender over vandreture i Danmark

| Aktivitet | Kategori af personer | Oplysninger | Formål | Retsgrundlag | Modtagere | Sletning | Sikkerhed |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Brugerkonti | Arrangører | Navn, e-mail, Google-id, logintidspunkt | Adgang til at oprette ture | Aftale (art. 6.1.b) | Nordicway, Google | Ved sletning af konto | Hashede adgangskoder, HTTPS, adgang kun for administrator |
| Deltagelse | Brugere | Bruger-id, tur-id, tidspunkt | Vise deltagelse, give arrangør besked | Aftale (art. 6.1.b) | Nordicway, arrangøren | 6 mdr. efter turen | Som ovenfor |
| Arrangøroplysninger på ture | Arrangører, kontaktpersoner | Navn, e-mail, hjemmeside | Kontakt mellem deltager og arrangør | Legitim interesse (art. 6.1.f) | Offentligt på siden | Når turen fjernes eller på anmodning | HTTPS |
| Indsamlede ture fra offentlige kilder | Arrangører | Navn, offentliggjorte kontaktoplysninger | Samle vandreture ét sted | Legitim interesse (art. 6.1.f) | Offentligt på siden | Når turen fjernes eller på anmodning | HTTPS |
| Kontaktformular | Alle | Navn, e-mail, besked | Besvare henvendelser | Legitim interesse (art. 6.1.f) | Nordicway (e-mail) | 12 mdr. | Som ovenfor |
| Serverlogs | Alle besøgende | IP-adresse, besøgte sider | Sikkerhed og drift | Legitim interesse (art. 6.1.f) | Nordicway | Efter Nordicways standard | Nordicways sikkerhed |

**Overførsel til tredjelande:** Google LLC (USA) for Google-login, dækket af EU-U.S. Data Privacy Framework.

**Databehandleraftaler:** Nordicway \[dato accepteret\], Google Data Processing Terms \[dato noteret\], \[SMTP-leverandør\].

**Sidst opdateret:** \[dato\]

## Checklist

- [ ] Privacy policy text reviewed and corrected by Petya
- [ ] `/privatliv` page live on production, linked in footer
- [ ] Privacy policy URL set on Google OAuth consent screen
- [ ] Nordicway databehandleraftale accepted, PDF saved
- [ ] SMTP provider identified, agreement accepted if external
- [ ] Fluent Forms entry storage turned off or retention set
- [ ] Signup, contact form and "Jeg kommer" notices in place
- [ ] User deletion removes `wp_event_attendees` rows (tested on staging)
- [ ] WordPress eraser and exporter registered for the attendee table
- [ ] Attendee retention cron running
- [ ] Cookie check on production: none for logged out visitors
- [ ] Fortegnelse saved as a file in the project folder
- [ ] Yearly reminder set to review the policy

Sources: [Erhvervsstyrelsen deprioritises enforcement of statistics cookies but consent is still required (Jurainfo)](https://jurainfo.dk/artikel/erhvervsstyrelsen-vil-fremover-nedprioritere-tilsynet-med-hjemmesiders-brug-af-simple-statistikcookies-men-kravet-om-samtykke-gaelder-stadig), [Cookiebekendtgørelsen overview (Cookiepopup.dk)](https://cookiepopup.dk/cookiebekendtgoerelsen/), [Datatilsynet](https://www.datatilsynet.dk)
