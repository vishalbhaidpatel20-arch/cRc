# 06 - Relationship types

## One-to-many (1-M)
**One row on one side can have many rows on the other, but each of those rows belongs to just one parent.**
The "many" table stores the parent's ID as a foreign key.

Examples here:
- One company has many contacts (`contacts.company_id`) and many tickets (`tickets.company_id`).
- One contact has many tickets and many interactions.
- One user (agent) is assigned many tickets (`tickets.assigned_to`).
- One ticket has many comments and many assignment records.
- One interaction can lead to many follow-ups.

Delete rules: RESTRICT on company/user history (you cannot delete a company that has tickets),
CASCADE for child records (deleting a ticket removes its comments), SET NULL for optional links
(deleting a user leaves the ticket, just unassigned).

## One-to-one (1-1)
**One row has at most one matching row on the other side.**
`contact_profiles.contact_id` is both the primary key and the foreign key to `contacts`, so a
contact can have only one profile, and a profile always belongs to one contact. It is kept
separate because the extra details (birthday, notes) are optional and rarely needed, which
keeps the main `contacts` table small. A contact without a profile is allowed (use LEFT JOIN).

## Many-to-many (M-M)
**Rows on both sides can be linked to many rows on the other side.** A database cannot store
this in one column, so a middle ("junction") table holds pairs of IDs; its composite primary key
stops the same pair being stored twice.

Examples here:
- `contact_modules`: one employee can use many modules, and one module is used by many employees.
- `user_companies`: one restricted agent can see many companies, and one company can be seen by many agents.

---

## हिंदी में (Hindi)

### एक-से-अनेक (1-M)
एक रिकॉर्ड के कई रिकॉर्ड हो सकते हैं, लेकिन हर रिकॉर्ड का सिर्फ़ एक ही मालिक (parent) होता है।
"अनेक" वाली टेबल में parent की ID (foreign key) रखी जाती है।
उदाहरण: एक कंपनी के कई कर्मचारी (contacts) और कई टिकट होते हैं; एक टिकट के कई कमेंट होते हैं;
एक एजेंट को कई टिकट मिलते हैं।
डिलीट के नियम: कंपनी या यूज़र का इतिहास सुरक्षित रखने के लिए RESTRICT (टिकट वाली कंपनी डिलीट नहीं होगी),
child रिकॉर्ड के लिए CASCADE (टिकट डिलीट होने पर उसके कमेंट भी हट जाते हैं),
और वैकल्पिक लिंक के लिए SET NULL (यूज़र हटने पर टिकट बना रहता है, बस unassigned हो जाता है)।

### एक-से-एक (1-1)
एक रिकॉर्ड का ज़्यादा से ज़्यादा एक ही जोड़ीदार रिकॉर्ड होता है।
`contact_profiles` में `contact_id` ही primary key और foreign key दोनों है, इसलिए एक कर्मचारी की
सिर्फ़ एक प्रोफ़ाइल हो सकती है। जन्मदिन, नोट्स जैसी वैकल्पिक जानकारी अलग टेबल में रखी जाती है ताकि
मुख्य `contacts` टेबल हल्की रहे। प्रोफ़ाइल के बिना भी कर्मचारी हो सकता है (इसीलिए LEFT JOIN)।

### अनेक-से-अनेक (M-M)
दोनों तरफ़ के रिकॉर्ड एक-दूसरे के कई रिकॉर्ड से जुड़ सकते हैं। इसे एक कॉलम में नहीं रखा जा सकता,
इसलिए बीच में एक तीसरी (junction) टेबल बनती है जिसमें IDs के जोड़े रहते हैं; composite primary key
एक ही जोड़ा दोबारा नहीं बनने देती।
उदाहरण: `contact_modules` - एक कर्मचारी कई मॉड्यूल इस्तेमाल कर सकता है और एक मॉड्यूल कई कर्मचारी;
`user_companies` - एक सीमित (restricted) एजेंट कई कंपनियाँ देख सकता है और एक कंपनी कई एजेंट देख सकते हैं।
