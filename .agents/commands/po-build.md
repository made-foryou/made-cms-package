---
description: Laat de implementer het goedgekeurde plan van een issue uitvoeren (stopt na de bouw)
argument-hint: <issue-nummer>
---
 
Voer de fase BOUW uit voor issue #$ARGUMENTS.
 
1. Controleer dat docs/plans/$ARGUMENTS/plan.md bestaat en dat ik het plan akkoord heb gegeven (in dit gesprek of in mijn opdracht). Zo niet: vraag er eerst om en stop.
2. Start de subagent `implementer` met: het issuenummer, de volledige issuetekst en het pad naar het plan.
3. Controleer het resultaat zelf: lees zijn samenvatting, draai `git status --short`, `git log --oneline -10` en `git diff --stat <hoofdbranch>...HEAD`, en vergelijk met het plan.
4. Rapporteer kort: wat is gedaan, afwijkingen van het plan, uitkomst van de checks.
5. Stel `/po-test $ARGUMENTS` voor als volgende stap en stop.
