# Delivery Workflow

## Cel

Ten workflow ustala minimalny rytm dostarczania zmian:
- mały batch zmian
- testy przed review
- review przez senior developera przed pushem
- demo dla PO po zakończonym sprincie
- retro po każdym sprincie

## Per zmiana

Każda zatwierdzona zmiana przechodzi przez ten sam loop:

1. Implementacja w małym vertical slice.
2. Targeted testy dla zmienionego procesu.
3. `make ci`.
4. Review diffu przez senior developera z użyciem [CHANGE_REVIEW_CHECKLIST.md](/Users/janbogdanski/projects/skrypty/fin/docs/CHANGE_REVIEW_CHECKLIST.md).
   Review note zapisujemy w `docs/reviews/YYYY-MM-DD-short-topic-review.md`.
5. Naprawa findings P0/P1 przed commitem.
6. Commit jednej logicznej zmiany.
7. Push na `main` albo branch roboczy.

## Zasady review

- Reviewer nie opisuje kodu od nowa. Szuka ryzyk, regresji i braków.
- Findings są ważniejsze niż summary.
- Każdy finding ma severity i wskazanie pliku.
- Brak findings też jest wynikiem review i powinien być zapisany.
- Jeśli zmiana dotyka auth, uploadu, billingu, danych użytkownika albo deklaracji podatkowej, review nie może zostać pominięty.

## Sprint end

Na koniec sprintu powstają dwa artefakty:

1. demo note dla PO według [SPRINT_DEMO_TEMPLATE.md](/Users/janbogdanski/projects/skrypty/fin/docs/SPRINT_DEMO_TEMPLATE.md)
2. retro według [SPRINT_RETRO_TEMPLATE.md](/Users/janbogdanski/projects/skrypty/fin/docs/SPRINT_RETRO_TEMPLATE.md)

Artefakty sprintowe zapisujemy w `docs/sprints/`, np.:
- `docs/sprints/sprint-17-demo.md`
- `docs/sprints/sprint-17-retro.md`

## Definition of done dla batcha

Batch jest zamknięty tylko jeśli:
- ma testy na poziomie procesu albo uzasadniony brak testu
- `make ci` jest zielone
- review zostało wykonane
- findings P0/P1 są naprawione albo jawnie odrzucone decyzją techniczną
