# Herhalende Bon Badge - Debug Info

## Status: ✅ Feature is Working Correctly

De herhalende bon badges zijn correct geïmplementeerd en werken. Het probleem was dat je test bon slechts **1 trekking** had, en het systeem toont alleen badges bij **2 of meer trekkingen**.

## Wat ik heb gevonden:

### 1. Je eerste test bon (ID 57)
```
Bon ID: 57
Date: 2025-12-01
Trekking Groep: 57
Aantal trekkingen in groep: 1
```

**Resultaat**: Geen badge, want er is maar 1 trekking (correct gedrag!)

### 2. Nieuwe test bon gemaakt (ID 61, 62, 63)
Ik heb een echte multi-trekking bon gemaakt voor testing:

```
Bon ID: 61 - Date: 2025-12-02 (vandaag)  → Positie 1/3
Bon ID: 62 - Date: 2025-12-03 (morgen)   → Positie 2/3
Bon ID: 63 - Date: 2025-12-04 (overmorgen) → Positie 3/3
```

Alle 3 behoren tot trekking groep 61.

## Hoe te testen:

### Stap 1: Open Dashboard
- Ga naar: http://localhost:8888/LuckyDays/dashboard.php
- Log in als admin

### Stap 2: Bekijk datum 2025-12-02 (vandaag)
- Je zou **2 bonnen** moeten zien:
  - "Bon 01 dec" (geen badge - geen herhalende bon)
  - **"Test 3-Trekkingen"** (MET badge: "1/3 Herhalende Bon")

### Stap 3: Check Browser Console
Open je browser console (F12 / Cmd+Option+I) en kijk naar de debug logs:
```javascript
Dashboard data received: {...}
Bon ID: 61 Trekking Info: {huidige_trekking: 1, aantal_trekkingen: 3, ...}
```

Als je deze logs ziet en trekking_info niet null is, dan werkt alles!

### Stap 4: Test andere dagen
- **2025-12-03**: Bon 62 zou badge "2/3 Herhalende Bon" moeten tonen
- **2025-12-04**: Bon 63 zou badge "3/3 Herhalende Bon" moeten tonen

## Waar worden de badges getoond?

### 1. ✅ Dashboard (dashboard.php)
- Compact badge boven elke bon
- Toont positie (1/3) + datumbereik
- Gradient achtergrond in speler kleur

### 2. ✅ Bon Detail Popup (dashboard.php)
- Volledige banner bovenaan popup
- Toont aantal trekkingen + datumbereik

### 3. ✅ Spelers Modal - Bonnen Tab (spelers.php)
- Banner in bonnen lijst van speler
- Toont aantal trekkingen + datumbereik

## Hoe werkt het systeem?

### Bij boninvoer:
1. Laatste rij invullen of 0 invoeren bij eerste nummer
2. Popup vraagt: "Voor hoeveel trekkingen geldt deze bon?"
3. Invoer: 0 of 1 = 1 trekking (geen duplicaten)
4. Invoer: 2 = vandaag + morgen
5. Invoer: 3 = vandaag + morgen + overmorgen (etc, max 7)

### Database logica:
- Alle gerelateerde bonnen krijgen hetzelfde `trekking_groep_id`
- Badge wordt alleen getoond als `aantal_trekkingen > 1`
- Positie wordt berekend op basis van datum: COUNT(*) + 1 WHERE date < current_date

### Code locaties:
- **Dashboard rendering**: `dashboard.php` regels 2622-2644
- **Bon detail popup**: `dashboard.php` rond regel 3200
- **Spelers modal**: `spelers.php` regels 1101-1117
- **API endpoint**: `api/get_dashboard_data.php` regels 50-81

## Debug logs toegevoegd:

Ik heb 2 console.log statements toegevoegd:
1. Regel 2586: `console.log('Dashboard data received:', data);`
2. Regel 2624: `console.log('Bon ID:', bon.id, 'Trekking Info:', bon.trekking_info);`

Deze logs helpen je te zien of de data correct binnenkomt.

## Volgende stappen:

1. **Test de feature** met de instructies hierboven
2. Als je de badge ziet op 2025-12-02 voor "Test 3-Trekkingen" → alles werkt! ✅
3. Als je de badge niet ziet → check browser console voor errors
4. **Maak een echte multi-trekking bon** via de normale flow:
   - Vul nummers in
   - Bij laatste rij of 0, kies aantal trekkingen (bijv. 3)
   - Bekijk de bonnen op alle 3 dagen
   - Elke dag zou de juiste positie moeten tonen (1/3, 2/3, 3/3)

## Test bestanden (kunnen verwijderd worden):

- `test_trekking_info.php` - Test trekking info berekening
- `create_test_multitrekking.php` - Maakte test bonnen
- `test_dashboard_api.php` - Test dashboard API logica

Deze kunnen veilig verwijderd worden als alles werkt.
