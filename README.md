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
