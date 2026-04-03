# ADR-005: Twig + Hotwire (Turbo + Stimulus) zamiast SPA

## Status
ACCEPTED

## Data
2026-04-03

## Kontekst

Frontend aplikacji to:
- Upload CSV → progress bar
- Tabela transakcji → filtrowanie, sortowanie
- Podgląd PIT-38 → drill-down do transakcji
- Dashboard → wykresy/podsumowania
- Formularze: dodawanie transakcji, straty z lat poprzednich, ustawienia

To jest apka **formularzowa** i **tabelaryczna** — nie real-time collaboration tool, nie drawing editor, nie social feed.

### Rozważane:
1. **React/Next.js SPA** — bogaty ekosystem UI, ale: osobny deployment, API versioning, CORS, hydration issues, SEO wymaga SSR, heavy bundle
2. **Vue + Inertia.js** — SPA-like z Symfony backend, ale: nadal JS framework overhead, build pipeline
3. **Twig + Hotwire** — server-rendered HTML, Turbo Frames dla partial updates, Stimulus dla JS behavior

## Decyzja

**Twig + Turbo (Hotwire) + Stimulus + Tailwind CSS**

### Jak to działa

**Twig** renderuje pełne strony HTML (server-side).

**Turbo Frames** dzielą stronę na fragmenty — kliknięcie w Turbo Frame aktualizuje tylko ten fragment (AJAX pod spodem, ale bez pisania JS):
```twig
{# Drill-down: kliknięcie w wiersz tabeli ładuje szczegóły transakcji #}
<turbo-frame id="transaction-detail">
    <a href="{{ path('transaction_detail', {id: tx.id}) }}">
        Pokaż szczegóły
    </a>
</turbo-frame>
```

**Turbo Streams** dla real-time aktualizacji (import CSV → progress):
```twig
<turbo-stream action="replace" target="import-progress">
    <template>
        <div id="import-progress">
            Zaimportowano {{ imported }}/{{ total }} transakcji...
        </div>
    </template>
</turbo-stream>
```

**Stimulus** dla interaktywnego JS (file upload drag&drop, sortowalna tabela, copy-to-clipboard):
```javascript
// controllers/file_upload_controller.js
import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["input", "preview"]

    drop(event) {
        event.preventDefault()
        this.inputTarget.files = event.dataTransfer.files
        this.previewTarget.textContent = `Wybrany plik: ${event.dataTransfer.files[0].name}`
    }
}
```

**Tailwind CSS** dla szybkiego stylowania — utility-first, zero custom CSS.

### Kiedy NIE wystarczy Hotwire?

Jeśli w przyszłości potrzebujemy:
- Complex drag & drop (Kanban board) → Stimulus + SortableJS
- Rich text editor → Trix (Hotwire native) lub TipTap
- Interactive charts → Chart.js via Stimulus controller
- Heavy client-side computation → to nie powinno się zdarzyć w tej apce

## Konsekwencje

### Pozytywne
- **Jeden stack** — PHP renderuje HTML, zero API versioning, zero CORS
- **Zero JS build dla większości stron** — Twig renderuje, Turbo nawiguje
- **SEO native** — server-rendered HTML, zero hydration
- **Accessibility native** — standard HTML forms, links, tables
- **Szybki development** — Twig partial to 10 linii, nie React component z hooks
- **Mniejszy bundle** — Turbo (~50KB) + Stimulus (~10KB) vs React ecosystem (~200KB+)
- **Progresywne ulepszanie** — bez JS strona nadal działa (forms submit, links navigate)

### Negatywne
- **Brak rich component ecosystem** — brak shadcn/ui equivalent, trzeba budować lub szukać
- **Complex UI patterns trudniejsze** — multi-step wizard, inline editing wymaga Stimulus
- **Brak type safety na frontendzie** — Twig nie ma TypeScript (mitigacja: Twig lint, PHPStan)
- **Mniej kandydatów na rynku** — Hotwire mniej popularny niż React (mitigacja: to jest PHP shop)

### Ścieżka migracji
Jeśli za rok potrzebujemy React dla specific feature:
1. Turbo Frame może ładować content z dowolnego URL
2. Budujemy React widget, serwujemy jako standalone
3. Turbo Frame embeduje go — zero conflict z resztą

## Uczestnicy decyzji

| Osoba | Stanowisko |
|---|---|
| Paweł [front] | "To jest apka formularzowa. Twig renderuje tabele i formularze szybciej niż React. Turbo daje dynamikę bez SPA." |
| Zofia [front] | "Accessibility z Twig to standard HTML. Z React SPA trzeba walczyć o WCAG. Tu mamy za darmo." |
| Mariusz Gil | "Mniej ruchomych części. Jeden deployment. Jak potrzebujecie React — embedujecie fragment." |
| Bartek [devops] | "Jeden Dockerfile, jeden build, jeden deploy. Nie dwa pipeline'y (API + SPA)." |
