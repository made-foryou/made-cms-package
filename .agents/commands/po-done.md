---
description: Rond een issue af met PR-tekst en issue-update, die pas na akkoord worden geplaatst
argument-hint: <issue-nummer>
---
 
Voer de fase AFRONDEN uit voor issue #$ARGUMENTS.
 
1. Controleer dat het testrapport GESLAAGD is en de review GOEDGEKEURD. Zo niet: zeg wat ontbreekt en stop.
2. Stel voor mij op: een PR-titel en -beschrijving (wat, waarom, testbewijs, verwijzing naar plan en review) en een korte statusupdate voor het issue. Vraag of de PR het issue automatisch moet sluiten (`Closes #$ARGUMENTS`).
3. Toon die teksten en wacht op mijn akkoord.
4. Pas daarna: push de branch, maak de PR met `gh pr create` en plaats de update met `gh issue comment`. Merge niet en sluit het issue niet zelf.
