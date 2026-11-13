# TM-xA network scale: observed device protocol

Source of every statement below: direct read and write exchanges with the physical scale at `192.168.0.150:33581` on 2026-11-08, plus public reverse-engineering notes for a related device family. There is no vendor protocol document. Anything not marked **verified on this device** is a guess.

Device identity from `UPL INF`: `TM-xA UA006 V3.15D`, MAC `0023F01B1577`, text encoding declared `UTF-8`.

## Framing (verified on this device)

- ASCII, fields separated by TAB, lines end with CRLF, each record ends with a trailing TAB.
- Read a section: send `UPL<TAB><TAG><TAB>CRLF`. The reply is `CRLF DWL<TAB><TAG><TAB>CRLF`, the records, then `END<TAB><TAG><TAB>CRLF`.
- Write records: send `DWL<TAB><TAG><TAB>CRLF CRLF <records> END<TAB><TAG><TAB>CRLF CRLF UPL<TAB>TIM<TAB>CRLF`. The device sends no acknowledgement of its own; the trailing `UPL TIM` request is answered with the clock frame ending `END<TAB>TIM<TAB>CRLF`, which proves the device processed the write. Always confirm by reading the section back.
- A write that names only some records changes only those records. Verified for `PLU` (other PLUs untouched) and `SCP` (all other rows byte-identical).
- The device sometimes refuses a new TCP connection right after a write or read; retry after a short pause.

## Sections read from this device

| Tag | Content seen | Status |
|---|---|---|
| `INF` | Model, firmware, MAC, encoding | read only |
| `PLU` | Products, one row each; field 1 number, 2 code, 4 unit, 5 price as `<digits>,<decimals>`, 15 name | read and write verified |
| `SCP` | Shortcut-key table: `SCP<TAB>layer<TAB>key<TAB>value<TAB>`; layers 0, 1, 2; keys 1 to 63; value `0` means empty | read and write verified |
| `LAB` | Label print formats: header `LAB<TAB>id<TAB>name<TAB>kind<TAB>width<TAB>height<TAB><field names…>` followed by `LAS` element rows `LAS<TAB>index<TAB>…<TAB>x<TAB>y<TAB>w<TAB>h`; factory formats PF-1 to PF-9 plus one custom clone (id 28) | read only; element columns not decoded |
| `BAR` | Barcode formats, for example `B201E500K500A000A000A000` | read only; template meaning unknown |
| `TMT` | Text strings (shop name, footer text); the first string is Arabic and reads back correctly | read only |
| `TMS` | Specification parameters, one `TMS<TAB>spec<TAB>value` row per spec | read only |
| `REP` / `RES` / `REE` | Sale log, one `REP` header plus one `RES` row per sale line; layout decoded below | read only |
| `KEY`, `D0`, `D1`, `SAL`, `SCF`, `TMR`, `UNT`, `BMP` | Empty on this device | no layout known |
| `DPT`, `CLS` | Department and class names | read only |

`KEY` is not the shortcut-key table; the shortcut keys are `SCP`.

## Verified shortcut-key write

Test on 2026-11-08: set layer 0, key 2 to `3999`, read `SCP` back, compare with the saved copy of the original section, then set the value back to `0` and compare again.

- Request: `DWL<TAB>SCP<TAB>CRLF CRLF SCP<TAB>0<TAB>2<TAB>3999<TAB>CRLF END<TAB>SCP<TAB>CRLF CRLF UPL<TAB>TIM<TAB>CRLF`
- Result: the read-back differed from the original only in that row, and after restoring `0` the section was byte-identical to the original.
- Not established: whether the value must be the PLU number or the PLU item code (the two are equal for the test PLUs 3998 and 3999, and the existing value `9001` at layer 0 key 1 matches an item code); whether pressing the key on the scale then sells that PLU.

## Not yet known

- Meaning of the `LAS` element columns, how to add an Arabic text element, and which element shows the product name.
- Any command that prints a test label; none was found.

## ERP implementation status (2026-11-08)

- `TmScaleConnection::sendShortcutKey()` writes one `SCP` row and confirms by reading the whole section back (only that row may differ, otherwise the send fails as unconfirmed). `readShortcutKeys()` returns the table.
- The settings screen assigns a mapped product to a key (layer 0 to 2, key 1 to 63), saves it, and has a "Send key to scale" action with status `pending`, `confirmed` or `unverified` stored in `scale_key_assignments` and an audit event on success.
- Verified through the screen and then by an independent read of the device: layer 0 key 2 = 3998 and layer 1 key 1 = 3999 (layer 0 key 1 stayed 9001).
- Not verified: pressing the physical key on the scale, the failure path of a key send with the device unreachable, whether layer 1 and 2 are reached with Shift.

## Sale-log layout (decoded 2026-11-08, consistent on all 37 records)

- `REP` header: fields 1 to 6 are year (2 digits), month, day, hour, minute, second; field 7 is a sequence number, unique per record (16 to 52 in the 37 records read).
- `RES` row: field 1 is the line number inside the sale (not the PLU); field 2 is the total price (2 decimals); field 3 is the item code, which ERP sets equal to the PLU number; field 7 is the weight (3 decimals, kg); field 15 is the unit price; the last non-empty field is the product name.
- Check: total equals unit price times weight in all 37 records, including the records created by pressing Confirm on PLU 3999 (14.00 per kg, weights 0.370 and 0.300). An earlier reading that field 2 was the unit price and field 7 the weight or total was wrong; it only looked plausible because the old records all had a unit price of 100.00.
- Not checked: that the displayed weight equals the physical weight placed on the scale (the owner did not state it), other measure units, and whether a record is written when the scale only prints without Confirm.
