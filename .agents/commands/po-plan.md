---
description: Laat de planner een implementatieplan schrijven voor een issue (stopt na het plan)
argument-hint: <issue-nummer>
---
 
Voer de fase PLAN uit voor issue #$ARGUMENTS.
 
1. Lees het issue met `gh issue view $ARGUMENTS --comments` en toets het aan docs/design/ en CLAUDE.md. Ontbreken er acceptatiecriteria, of is er iets onduidelijk? Stel dan eerst je vragen aan mij en stop.
2. Start de subagent `planner` met: het issuenummer, de volledige issuetekst en reacties, relevante ontwerpverwijzingen en het doelpad docs/plans/$ARGUMENTS/plan.md.
3. Lees het plan zelf, controleer het tegen het ontwerp en controleer met `git status --short` dat alleen het plan is aangemaakt.
4. Geef me een samenvatting: aanpak, bestanden, risico's, open vragen en omvang.
5. Stop en wacht op mijn akkoord. Werk het issue pas bij na akkoord.
