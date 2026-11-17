---
description: Laat de tester het resultaat van een issue testen tegen de acceptatiecriteria (stopt na het rapport)
argument-hint: <issue-nummer>
---
 
Voer de fase TEST uit voor issue #$ARGUMENTS.
 
1. Start de subagent `tester` met: het issuenummer, de volledige issuetekst, het pad naar het plan en het pad voor het rapport (docs/plans/$ARGUMENTS/test-rapport.md).
2. Lees het rapport zelf en controleer met `git status --short` dat alleen het rapport nieuw is en er geen code is gewijzigd.
3. Rapporteer de uitslag en de bevindingen.
4. Bij GEFAALD of blockers: stel voor de implementer opnieuw te laten werken met de bevindingen (fixronde 1 of 2; daarna eerst met mij overleggen) en wacht op mijn akkoord.
5. Bij GESLAAGD: stel `/po-review $ARGUMENTS` voor. Stop.
