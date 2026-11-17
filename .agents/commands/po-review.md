---
description: Laat de reviewer de codewijzigingen van een issue reviewen (stopt na de review)
argument-hint: <issue-nummer>
---
 
Voer de fase REVIEW uit voor issue #$ARGUMENTS.
 
1. Start de subagent `reviewer` met: het issuenummer, de volledige issuetekst, het pad naar het plan, het pad naar het testrapport (indien aanwezig) en het pad voor de review (docs/plans/$ARGUMENTS/review.md).
2. Lees de review zelf en controleer met `git status --short` dat alleen de review nieuw is en er geen code is gewijzigd.
3. Rapporteer het oordeel en de bevindingen, gesorteerd op ernst. Beoordeel zelf of de blockers terecht zijn.
4. Bij WIJZIGINGEN NODIG: stel voor de implementer opnieuw te laten werken met de blockers (fixronde 1 of 2; daarna eerst met mij overleggen) en wacht op mijn akkoord.
5. Bij GOEDGEKEURD: stel `/po-done $ARGUMENTS` voor. Stop.
