# IT Helpdesk — User Instruction Manual

**Who this is for:** every staff member who uses the IT Helpdesk  
**App address:** `http://localhost:5173/` (on the office network: `http://192.168.20.3:5173/`)  
**Support:** contact IT Support if you cannot sign in or your account is pending approval

---

## 1. What this system is for

Use the IT Helpdesk to:

- Raise IT problems and requests (hardware, software, network, printers, access, etc.)
- Track progress on your tickets
- Confirm when IT has fixed your issue
- (For IT staff) manage, assign, resolve and report on support work

---

## 2. Getting started

### 2.1 Sign in

1. Open the app in your browser.
2. Enter your **username** and **password**.
3. Click **Sign in**.

If you forget your password, ask an IT administrator to reset it. Self-service password reset is not available yet.

### 2.2 Register a new account (if allowed)

1. On the login page, open **Register**.
2. Fill in:
   - Full name  
   - Branch  
   - Employee ID  
   - EPF number  
   - Username  
   - Password  
3. Submit the form.

**Important:** a new account usually needs **IT approval** before you can use the system. After registering, wait for IT to activate your account, then sign in.

### 2.3 Roles at a glance

| Role | Main use |
| ---- | -------- |
| **Employee** | Create and track your own tickets; confirm when a fix works |
| **Technician (IT Support)** | Work all tickets, update status, resolve issues |
| **Supervisor** | Same as technician, plus assign tickets and view reports |
| **Administrator / Super Admin** | Manage users, branches, categories, settings, reports and audit logs |

You only see menu items your role is allowed to use.

---

## 3. For employees (everyone)

### 3.1 Dashboard

After login you see your **Dashboard**:

- Summary of your open / recent tickets  
- Shortcuts: **Create IT Ticket**, **My Tickets**, **Knowledge Base**, **Contact IT**

### 3.2 Create a ticket

1. Go to **Create Ticket** (or use the dashboard shortcut).
2. Fill in the form:

| Field | What to enter |
| ----- | ------------- |
| **Subject** | Short summary (e.g. “Laptop will not charge”) |
| **Category** | Type of issue (Hardware, Software, Network, Printer, …) |
| **Priority** | How urgently it affects your work |
| **Description** | What happened, when it started, and what you already tried |
| **Preferred contact method** | How IT should reach you (optional) |
| **Branch** | Where the issue is happening |
| **Location / desk** | Building, floor or desk number (optional) |
| **Additional details** | Asset tags, error messages, etc. (optional) |
| **Attachments** | Screenshots or photos (optional) |

3. If the category needs **item request** details (e.g. Hardware / Printer), also choose:
   - Issue type (new request, fault, replacement, …)  
   - Item type (laptop, printer, …)  
   - Extra text if you choose “Other”

4. Click **Submit ticket**.
5. Note the **ticket number** (e.g. `IT-2026-000123`). Keep it for follow-up.

**Tips for a fast fix**

- Be specific in the subject and description.  
- Attach a clear screenshot of any error.  
- Choose the correct **branch** and desk so IT can find you.  
- Do not put passwords in the ticket.

### 3.3 View and track your tickets

1. Open **My Tickets**.
2. Use search / filters if needed.
3. Click a ticket to open full details:
   - Status and priority  
   - Conversation with IT  
   - Attachments  
   - Timeline of updates  

### 3.4 Reply and add files

On a ticket that is still open:

- Type a message in the comment box and send it.  
- Upload extra attachments if IT asks for more information.

### 3.5 Confirm or reject a resolution

When IT marks your ticket **Resolved**, you will be asked to confirm:

1. Open the ticket (or the verification prompt).
2. Check that the problem is actually fixed.
3. Choose:
   - **Confirm** — issue is fixed; ticket will close  
   - **Reject** — issue is not fixed; IT will continue working  

Please respond promptly so tickets do not stay waiting on you.

### 3.6 Knowledge Base

Open **Knowledge Base** for common how-to articles before raising a ticket (password tips, printer setup, VPN, etc.).

### 3.7 Profile

Open **Profile** to review your name, branch and account details.

### 3.8 Notifications

Use the **bell icon** in the top bar to see updates (ticket created, status changes, resolution waiting for your confirmation).

---

## 4. For IT Support (Technicians)

### 4.1 Staff area

Sign in with an IT account. Use the **admin / service desk** menu:

- **Dashboard** — overall queue health  
- **Tickets** — all support tickets  
- **My Assigned Tickets** — work assigned to you  

### 4.2 Pick up a ticket

1. Open **Tickets** or **My Assigned Tickets**.
2. Filter by status, branch, priority or date as needed.
3. Open the ticket workspace to see:
   - Requester and branch  
   - Full description and attachments  
   - Conversation  
   - Allowed status actions  

### 4.3 Update status

Typical flow:

`New` → `Assigned` → `In Progress` → (`On Hold` / `Awaiting User` if needed) → `Resolved` → (employee confirms) → `Closed`

1. Open the status action on the ticket.
2. Choose the next status.
3. When resolving, enter a short **resolution summary** of what you did.
4. Save.

### 4.4 Assign a ticket (Supervisor+)

1. Open the ticket.
2. Use **Assign**.
3. Select the IT staff member.
4. Confirm.

### 4.5 Talk to the employee

- Use the conversation panel for updates the employee can see.  
- Use **internal notes** only when the message should stay inside IT.

### 4.6 Attachments

- Download employee files from the ticket.  
- Upload your own screenshots or guides if helpful.  
- If download fails, refresh the page and try again; report persistent failures to an administrator.

### 4.7 Resolve a ticket

1. Choose **Resolve** / move status to **Resolved**.
2. Write a clear resolution summary.
3. The employee must **Confirm** or **Reject**.  
   - Confirmed → ticket closes  
   - Rejected → ticket returns for more work  

---

## 5. For Supervisors and Administrators

### 5.1 Users

**Users** menu (Administrator+):

- Search and filter staff by branch / role / status  
- Create users  
- Approve pending registrations  
- Update role, branch, department  
- Deactivate accounts  
- Reset passwords when someone is locked out  

### 5.2 Branches

Maintain company locations used on registration and tickets (name, code, city, active/inactive).

### 5.3 Categories

Maintain the service catalogue employees see when creating tickets (Hardware, Software, Printer, …).  
Some categories can require item-request fields.

### 5.4 Reports

**Reports** (Supervisor can view; Administrator can export):

1. Set date range and optional filters (branch, category, priority, staff, status).
2. Review KPIs and charts on screen.
3. Click **Export CSV** or **Export PDF**.

Exports include:

- Summary KPIs and charts  
- **Full ticket details**: who requested, subject, description, branch, status, resolution, dates, and more  

Use CSV for Excel analysis; use PDF for sharing a printable pack.

### 5.5 Audit Logs

Administrators can review security and admin actions (logins, user changes, exports, etc.).

### 5.6 Settings

Organisation name, support contact details, registration options, and related system settings.

---

## 6. Ticket statuses (simple meanings)

| Status | Meaning |
| ------ | ------- |
| **New** | Just submitted |
| **Assigned** | Given to IT staff |
| **In Progress** | Being worked on |
| **On Hold** | Paused (waiting on parts, vendor, etc.) |
| **Awaiting User** | Waiting for your reply or action |
| **Resolved** | IT believes it is fixed — you must confirm |
| **Closed** | Finished and confirmed |
| **Cancelled** | Cancelled / withdrawn |

---

## 7. Best practices

**Employees**

- One issue per ticket when possible.  
- Include ticket number in any email or chat follow-up.  
- Confirm or reject resolutions quickly.  

**IT staff**

- Keep status up to date.  
- Write resolution notes the employee can understand.  
- Prefer clear public comments; reserve internal notes for sensitive detail.  

**Managers / Admins**

- Approve new users promptly.  
- Keep branches and categories accurate.  
- Export reports regularly for management review.  

---

## 8. Common problems

| Problem | What to do |
| ------- | ---------- |
| Cannot sign in | Check username/password; ask admin if account is approved and active |
| Registration submitted but cannot log in | Wait for IT approval |
| Attachments will not download | Hard-refresh the page; try again; tell IT if it still fails |
| Cannot see admin menus | Your role does not include those permissions |
| Page will not load | Confirm you are using port **5173** and the app server is running |
| Wrong branch on a ticket | Choose the correct branch when creating; ask IT to correct if already submitted |

---

## 9. Quick reference — main menus

### Employee menu

- Dashboard  
- My Tickets  
- Create Ticket  
- Knowledge Base  
- Profile  

### IT / Admin menu (role-based)

- Dashboard  
- Tickets  
- My Assigned Tickets  
- Users  
- Branches  
- Categories  
- Reports  
- Audit Logs  
- Settings  

---

## 10. Need help with the system itself?

If the Helpdesk application is down, slow, or showing errors:

1. Note the time and what you were doing.  
2. Capture a screenshot if possible.  
3. Contact IT Support / the system administrator.

---

*Document version: 1.0 — IT Helpdesk user guide for all staff*
