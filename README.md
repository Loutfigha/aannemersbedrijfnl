# Aannemersbedrijf NL — website

Statische website voor Aannemersbedrijf NL, geëxporteerd uit Claude Design en omgezet naar losse bestanden zodat hij in Git beheerd en overal gehost kan worden. Er is geen build-stap nodig.

## Structuur

```
index.html                  De pagina (inhoud, stijl en logica)
assets/img/                 Foto's en logo's
assets/fonts/               Archivo en Cormorant Garamond (lokaal, geen Google-verzoeken)
assets/js/                  Pagina-runtime, image-slot en React 18.3.1
.image-slots.state.json     Opslag voor fotovakken (leeg)
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

Teksten, cijfers en kleuren staan in `index.html`. Foto's vervang je door een bestand in `assets/img/` te overschrijven met dezelfde naam. Werk je liever verder in Claude Design, exporteer dan opnieuw en vervang de bestanden in deze repo.
