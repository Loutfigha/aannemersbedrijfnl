# Aannemersbedrijf NL — website

Statische website voor Aannemersbedrijf NL, geëxporteerd uit Claude Design en omgezet naar losse bestanden zodat hij in Git beheerd en overal gehost kan worden. Er is geen build-stap nodig.

## Structuur

```
index.html                  De pagina (inhoud, stijl, logica en SEO-metadata in de <head>)
404.html                    Eigen foutpagina ("Pagina niet gevonden")
robots.txt                  Regels voor zoekmachines, verwijst naar de sitemap
sitemap.xml                 Sitemap met de homepage
llms.txt                    Korte samenvatting van de site voor AI-assistenten
site.webmanifest            Naam en iconen van de site
favicon.ico                 Favicon (verder in assets/icons/)
.htaccess                   Hostinger/Apache: 404-pagina, compressie en caching
assets/img/                 Foto's, logo's en de deelafbeelding (og-image.jpg, 1200x630)
assets/icons/               Favicon-, app- en touch-iconen
admin/                      Beheeromgeving (PHP, geen database) op /admin/
content/default.js          Standaardinhoud van de site (teksten, lijsten, foto's)
content/live.php            Levert de door de klant aangepaste inhoud aan de pagina
contact.php                 Verstuurt het contactformulier per e-mail
uploads/                    Foto's die de klant uploadt (ontstaan op de server, niet in Git)
assets/fonts/               Archivo en Cormorant Garamond (lokaal, geen Google-verzoeken)
assets/js/                  Pagina-runtime en React 18.3.1
.nojekyll                   Nodig voor GitHub Pages
```

## Lokaal bekijken

Open de map in een terminal en start een simpele webserver (direct dubbelklikken op `index.html` werkt niet overal):

```bash
python3 -m http.server 8000
```

Ga daarna naar http://localhost:8000.

## Naar GitHub

```bash
git init
git add .
git commit -m "Eerste versie website"
git branch -M main
git remote add origin https://github.com/<gebruikersnaam>/aannemersbedrijf-nl.git
git push -u origin main
```

## Publiceren (kies één)

**GitHub Pages** — Repository → Settings → Pages → Source: *Deploy from a branch*, branch `main`, map `/ (root)`. Na een minuut staat de site op `https://<gebruikersnaam>.github.io/aannemersbedrijf-nl/`.

**Netlify** — Add new site → Import an existing project → kies de repo. Build command leeg laten, publish directory `/`.

**Vercel** — Add New → Project → kies de repo. Framework preset *Other*, geen build command, output directory leeg.

Bij Netlify en Vercel wordt elke `git push` automatisch gepubliceerd.

## Eigen domein

Voeg het domein toe in de instellingen van je host (GitHub Pages: Settings → Pages → Custom domain) en zet bij je domeinregistrar de DNS-records die de host opgeeft.

## Aanpassen

Teksten, cijfers en kleuren staan in `index.html`. De titel, beschrijving, canonical-tag, social-tags en structured data (schema.org) staan bovenin de `<head>`; pas die mee aan als de bedrijfsgegevens veranderen. Foto's vervang je door een bestand in `assets/img/` te overschrijven met dezelfde naam. Werk je liever verder in Claude Design, exporteer dan opnieuw en vervang de bestanden in deze repo.

## Domein

De canonical-tags, `og:url`, `og:image`, `sitemap.xml`, `robots.txt` en `llms.txt` gebruiken `https://aannemersbedrijf-nl.nl`. Wijzigt het domein, vervang het dan in al deze bestanden:

```bash
grep -rl "aannemersbedrijf-nl.nl" --include="*.html" --include="*.xml" --include="*.txt" . | xargs sed -i 's#https://aannemersbedrijf-nl.nl#https://NIEUW-DOMEIN.nl#g'
```

Het commando vervangt alleen URL's die met `https://` beginnen; het e-mailadres blijft ongewijzigd.

## Nog in te vullen

- Echte klantreviews (bijvoorbeeld via Trustpilot); de reviewsectie is verwijderd zolang er geen echte reacties zijn.
- Werkgebied, plaats en eventueel adres, zodat ze op de site en in de LocalBusiness-schema kunnen komen.
- Projectfoto's, locatie en doorlooptijd per project.
- Privacyverklaring, cookiebeleid en algemene voorwaarden (de footerlinks verwijzen nog nergens naartoe).

## Beheeromgeving (/admin/)

De klant logt in op `https://aannemersbedrijf-nl.nl/admin/` en past teksten, cijfers, diensten, voordelen, werkwijze, veelgestelde vragen, projecten en foto's aan. De wijzigingen staan direct op de site. Telefoonnummer, e-mailadres, koppen, SEO-gegevens en het ontwerp blijven in de code.

**Hoe het werkt**
- `content/default.js` bevat de standaardinhoud. `index.html` laadt die en daarna `content/live.php`, dat de opgeslagen aanpassingen van de klant levert. Zo zijn aanpassingen van de klant en code-updates via Git los van elkaar: een Git-deploy overschrijft de inhoud van de klant niet.
- De opgeslagen inhoud, accounts, sessies en back-ups staan in `ab-private/` naast `public_html` (buiten het web). Lukt dat niet, dan wordt `admin/data/` gebruikt, afgeschermd met `.htaccess` en een PHP-beveiligingsregel in elk bestand.
- Foto's worden bij het uploaden verkleind (maximaal 2000 px) en als WebP opgeslagen in `uploads/`.
- Bij elke keer opslaan wordt de vorige versie bewaard (laatste 40); terugzetten kan onder "Back-ups".

**Eerste keer instellen**
1. Wacht tot de deploy klaar is en open `/admin/`. Je komt op "Eerste installatie".
2. Vul de installatiecode in (alleen bekend bij de eigenaar; in de repo staat alleen de hash in `admin/setup.hash`), kies een e-mailadres en een wachtwoord van minimaal 12 tekens.
3. Maak daarna onder **Account** een tweede gebruiker aan voor de klant.
4. De installatiecode werkt alleen zolang er nog geen account bestaat.

**Wachtwoord kwijt?** Verwijder via Bestandsbeheer `ab-private/users.php` (of `admin/data/users.php`). Daarna werkt `/admin/` weer als eerste installatie, met dezelfde installatiecode. Wil je een nieuwe code, bereken dan `sha256` van de nieuwe code en zet die in `admin/setup.hash`.

**Controleer na de eerste deploy**
- Sla een aanpassing op, trigger in hPanel een nieuwe deploy en controleer dat de aanpassing blijft staan.
- Zet af en toe een back-up van `ab-private/` en `uploads/` weg.

**Eisen**: PHP 8.0 of hoger met de extensies GD (foto's) en bij voorkeur mbstring en exif.

## Beveiliging en onderhoud van de beheeromgeving

- **Nooit opschonen op de server.** Voer in `public_html` geen `git clean`, her-clone of "verwijder alles" uit. `uploads/` (foto's) en, als het buiten de webmap niet lukt, `admin/data/` staan niet in Git en gaan dan verloren. Zet regelmatig een back-up van `uploads/` en `ab-private/` weg.
- **Privémap controleren.** Bij voorkeur staan de gegevens in `ab-private/` naast `public_html`. Staat onder **Account** een waarschuwing over `admin/data`, dan kon die map niet worden aangemaakt; laat dat controleren.
- **Inloggen is beperkt:** maximaal 10 pogingen per 15 minuten per IP-adres en per account+IP, en 200 per uur per account. Daarna volgt een blokkade van maximaal een kwartier.
- **Wachtwoord wijzigen** beëindigt alle andere sessies. Een gebruiker toevoegen vraagt het eigen wachtwoord.
- **PHP-fouten** worden naar `ab-private/php-error.log.php` geschreven (niet naar een openbaar `error_log`).
- Gelijktijdig bewerken door twee gebruikers: de laatste die opslaat wint; onder Back-ups staat de vorige versie.
- Staat het domein achter een CDN of proxy, controleer dan of `REMOTE_ADDR` het echte IP-adres van de bezoeker is; anders delen alle bezoekers één inlogbucket.
