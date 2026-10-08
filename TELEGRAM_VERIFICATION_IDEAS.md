# Telegram Verification Alternatives & Fallback Architecture Ideas

This document serves as an architectural archive of all verification models discussed for Moodle phone/account verification using Telegram, specifically designed for scenarios where the site administrator cannot or does not want to pay for the commercial **Telegram Gateway API** (`gatewayapi.telegram.org`).

---

## Model 1: One-Way Inbound Deep-Link Webhook (Selected Primary Model)

### Summary
The fastest, zero-cost, zero-typing verification model for public HTTPS servers.

### Flow
1. User enters name, email, password, and phone number on Moodle (`local/telegramotp/index.php`).
2. Moodle generates a random session token: `v_32hexcharacters` and stores it in `local_telegramotp_requests` as `pending`.
3. The page presents a button: **[ 🚀 Verify via Telegram ]** linking to:
   `https://t.me/<sitebotusername>?start=reg_32hexcharacters`
4. The user taps the button. Telegram opens and displays the **[ Start ]** button.
5. User taps **Start**. Telegram automatically sends `/start reg_32hexcharacters` to the site bot.
6. Telegram HQ immediately delivers an HTTPS POST webhook to Moodle (`telegramconnect.php` or `webhook.php`).
7. Moodle matches the token, auto-creates the account (or validates the session), marks status as `verified`, saves the student's `chat_id`, and stores the verified phone into the custom profile field `telegram_phone`.
8. The browser page (polling `ajax.php?action=check_status` every 1.5 seconds) detects the verified state and automatically logs the student in, redirecting to `/my/`.

### Pros & Cons
* **Pros:** 100% free; under 2 seconds total time; zero typing; zero copy-paste errors; immediately links bot for `message_telegram` LMS notifications.
* **Cons:** Requires Moodle to have a public HTTPS URL accessible by Telegram's cloud servers.

---

## Model 2: Reverse OTP Code via Bot (Firewall / No-Webhook Fallback)

### Summary
Ideal when Moodle cannot receive public inbound webhooks (e.g. firewalled intranet, localhost, or strict network policies).

### Flow
1. Student enters registration details on Moodle.
2. Moodle displays a deep-link: `https://t.me/<sitebotusername>?start=getcode_<session_id>`.
3. Student taps the link and hits **Start**.
4. The bot (via polling or outbound message) immediately sends a clean 6-digit numeric OTP to the student's Telegram chat:
   > 🔐 *Your Moodle registration verification code is:* **582 194** *(valid for 5 minutes)*
5. The student types `582194` into the Moodle registration form.
6. Moodle verifies the code against the session and completes account creation.

### Pros & Cons
* **Pros:** 100% free; works even when inbound webhooks cannot reach Moodle; 6-digit numbers are easy to remember and type on mobile.
* **Cons:** Requires the student to switch back and type 6 digits.

---

## Model 3: Two-Way Stateless Encrypted Token Relay (Cryptographic Handshake)

### Summary
A decentralized, stateless cryptographic challenge-response protocol. Useful when the Telegram Bot runs on an independent microservice (e.g. Cloudflare Worker, AWS Lambda, or n8n workflow) that has no access to Moodle's database.

### Cryptographic Concept
```
Moodle (Secret Key K)
  └─► Encrypt(Phone + SessionID + Timestamp, K) = Token A
        └─► Student sends Token A to Bot: /start TokenA
              └─► Bot (Secret Key K) decrypts Token A
                    └─► Validates Timestamp < 5 minutes
                    └─► Encrypt(Phone + "VERIFIED" + ChatID + Timestamp, K) = Token B
                          └─► Bot sends Token B to Student
                                └─► Student pastes Token B into Moodle
                                      └─► Moodle decrypts Token B with K -> Account Created!
```

### Technical Specification
* **Algorithm:** AES-128-CBC or HMAC-SHA256 with Base64URL encoding.
* **Payload A:** `E_128(phone + ":" + time() + ":" + random_bytes(8), secret_key)`
* **Payload B:** `E_128(phone + ":VERIFIED:" + chat_id + ":" + time(), secret_key)`
* **Replay Protection:** Timestamp expired if `abs(time() - payload_time) > 300` seconds.

### Pros & Cons
* **Pros:** Completely stateless; zero database lookups between bot and Moodle; tamper-proof; works across completely disconnected networks.
* **Cons:** Higher user friction (double copy-paste of cryptographic strings on mobile).

---

## Model 4: Strict Verified Phone Contact Card (Anti-Spoofing Handshake)

### Summary
Prevents a malicious user from registering with someone else's phone number on Moodle while using their own Telegram account.

### Flow
1. User enters phone number `+966501234567` on Moodle.
2. User opens the bot via deep-link `/start reg_<token>`.
3. The bot does not verify immediately on `/start`. Instead, it replies with an interactive reply markup keyboard:
   ```json
   {
     "keyboard": [[
       { "text": "📱 Share Verified Phone Number", "request_contact": true }
     ]],
     "one_time_keyboard": true,
     "resize_keyboard": true
   }
   ```
4. When the user taps the button, Telegram's app transmits an official `contact` object signed by Telegram containing their actual phone number.
5. Moodle receives the contact object:
   - If `contact.phone_number` matches `+966501234567`: Verified!
   - If numbers do not match: Bot replies: *"❌ Phone number mismatch. Please use the Telegram account registered to this phone number."*

### Pros & Cons
* **Pros:** 100% fraud-proof; guarantees that the Telegram account belongs to the exact phone number submitted on Moodle; 100% free.
* **Cons:** Requires 1 extra tap (tapping the "Share Phone" button in Telegram).

---

## Model 5: Inbound Pre-filled Message Pattern (`qlogin_shomokh` / `wa.me` Port)

### Summary
The direct port of the WhatsApp `qlogin_shomokh` mechanism to Telegram.

### Flow
1. Moodle generates an authentication action phrase, e.g., `MOODLE VERIFY 982140`.
2. Moodle links user to: `https://t.me/<sitebotusername>?text=MOODLE%20VERIFY%20982140`.
3. User sends the text message to the bot.
4. Moodle webhook receives the text, parses regex `/\bMOODLE\s+VERIFY\s+(\d{6})\b/i`, matches the session, and verifies.

---

## Summary Comparison Matrix

| Model | Primary Advantage | Typical Use Case | User Friction | Cost |
| :--- | :--- | :--- | :--- | :--- |
| **Model 1: Inbound Webhook** | Sub-second, zero typing | Production Moodle with HTTPS | Minimal (1 Tap) | **$0.00** |
| **Model 2: Reverse OTP Code** | Firewall bypass | Intranets / Private networks | Low (Type 6 digits) | **$0.00** |
| **Model 3: Stateless Crypto** | No shared DB needed | Disconnected microservices/n8n | Moderate (Copy-paste) | **$0.00** |
| **Model 4: Strict Contact Card**| 100% anti-spoofing | High-security institutions | Minimal (2 Taps) | **$0.00** |
| **Model 5: Pre-filled Text** | WhatsApp familiar | Alternative bot command style | Low (1 Tap send) | **$0.00** |
