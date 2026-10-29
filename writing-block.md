# PROJECT: SEND Ghana Administrative Management System (SEND-AMS)

## 1. Project Overview

Act as a senior full-stack software architect, Laravel developer, database architect, cybersecurity engineer, and UI/UX designer.

Design and develop a secure, professional, production-ready, web-based Administrative Management System for **SEND Ghana**, a civil society organization operating in Ghana.

The system will digitize administrative operations, improve record management, strengthen internal controls, and provide accountability over organizational resources.

The initial system must consist of three primary operational modules:

1. Vendor Records Management
2. Driver Log and Vehicle Movement Management
3. Asset Register and Asset Lifecycle Management

These modules must be supported by centralized authentication, user administration, role-based access control, approval workflows, comprehensive audit trails, reporting, notifications, data protection, and system administration capabilities.

The application must support multiple users, departments, organizational locations, and administrative roles.

**Project name:** SEND Administrative Management System

**Short name:** SEND-AMS

**Primary organization:** SEND Ghana

**Deployment type:** Secure, centrally hosted web application

**Primary users:** Administrative staff, management, drivers, IT administrators, finance staff, auditors, and authorized employees.

---

## 2. Recommended Technology Stack

Use the following technologies:

### Backend
- PHP with a currently supported stable Laravel release
- MySQL 8 or compatible supported version
- Laravel Eloquent ORM
- Laravel Policies and Gates
- Laravel Queues and scheduled jobs
- RESTful APIs where appropriate
- Database transactions for critical operations

### Frontend
- Laravel Blade templates
- Bootstrap 5
- Alpine.js for lightweight interactivity
- JavaScript for dynamic forms and validation
- Chart.js for dashboards and analytics
- Responsive design for desktops, tablets, and mobile devices

### Security and infrastructure
- Laravel authentication and authorization
- Multi-factor authentication
- HTTPS/TLS
- Secure session management
- Server-side validation
- Encrypted sensitive fields where necessary
- Centralized application logging
- Encrypted backups
- Configurable retention policies

Prefer established, actively maintained libraries rather than unnecessarily complex custom implementations.

The application must use a modular architecture allowing additional administrative modules to be introduced without restructuring the entire system.

---

# 3. USER ACCOUNTS AND ACCESS MANAGEMENT

Develop a centralized identity and access management system.

## 3.1 User Accounts

Every user must have an individual account.

Required account attributes:
- Unique user ID
- Full name
- Staff ID (optional)
- Official email address
- Phone number
- Department
- Job title
- Assigned office/location
- Assigned role(s)
- Account status
- Last successful login
- Account creation date
- Last account update date
- Created by
- Updated by

Account statuses:
- Active
- Suspended
- Disabled
- Pending activation

Users must never share accounts.

## 3.2 User Roles

Implement configurable role-based access control (RBAC).

Default roles:

**Super Administrator**
- Manage application configuration
- Manage user accounts, roles, and permissions
- Configure security settings
- Review system audit logs
- Manage system maintenance and backup configurations

**IT Administrator**
- Manage technical operations and user provisioning
- Monitor system health
- Review technical and security events
- Troubleshoot application problems
- No automatic access to confidential business records unless separately authorized

**Administrative Manager**
- Manage vendors, vehicles, and assets
- Approve relevant administrative transactions
- Generate administrative reports
- Assign responsibilities

**Administrative Officer**
- Create and update authorized records
- Register vendors
- Record vehicle activities
- Maintain asset records
- Submit transactions for approval

**Finance Officer**
- View authorized vendor financial information
- Review invoices, contracts, and payment records
- Review asset purchase values
- Generate financial-related administrative reports

**Driver**
- View assigned vehicle details
- Submit vehicle movement logs
- Record trips, mileage, fuel usage, and incidents
- View personal driving history
- Cannot access unrelated vendor or asset records

**Staff/User**
- View personal asset assignments
- Acknowledge asset receipt and return
- Submit authorized administrative requests
- View their own request histories

**Auditor**
- Read-only access to specifically authorized records
- Access audit histories and approved reports
- Cannot edit or delete operational records

**Management**
- View approved management dashboards and reports
- Approve transactions assigned to their role
- Access consolidated administrative analytics within their authorization scope

A user may hold multiple roles where justified.

## 3.3 Granular Permissions

Permissions must be action-based and resource-specific.

Examples:
- vendors.view
- vendors.create
- vendors.update
- vendors.approve
- vendors.archive
- vehicles.view
- vehicles.create
- driver_logs.create
- driver_logs.review
- driver_logs.approve
- assets.view
- assets.create
- assets.assign
- assets.transfer
- assets.dispose
- reports.export
- audit_logs.view
- users.manage
- settings.manage

Access must be enforced on the server for every protected route, API, operation, and data query.

Use least privilege and deny access by default.

## 3.4 Authentication

Implement:
- Secure login and logout
- Multi-factor authentication for privileged accounts
- Password reset through verified email
- Strong password policy
- Account lockout or progressive throttling
- Login rate limiting
- Session expiration
- Session invalidation after password changes
- Ability to revoke active sessions
- Optional Microsoft Entra ID (Microsoft 365) single sign-on

If Microsoft Entra ID integration is implemented, use supported OAuth 2.0/OpenID Connect flows and validate tokens correctly.

---

# 4. MODULE ONE: VENDOR RECORDS MANAGEMENT

## 4.1 Objective

Develop a centralized vendor database containing all suppliers, contractors, consultants, and service providers engaged by SEND Ghana.

The module should support vendor registration, verification, classification, document management, performance assessment, approval, and lifecycle management.

## 4.2 Vendor Registration

Capture:

### Vendor identification
- Auto-generated vendor ID
- Registered business name
- Trading name
- Vendor type
- Business registration number
- Tax Identification Number (TIN), where applicable
- VAT registration status, where applicable
- Date of registration
- Business address
- Region
- City/town
- Country
- Website

### Contact details
- Primary contact person
- Designation
- Phone number
- Email address
- Alternative contact person
- Alternative phone/email

### Vendor classification
- Goods supplier
- Service provider
- Consultant
- Contractor
- Logistics/transport provider
- ICT vendor
- Maintenance provider
- Other configurable categories

Allow administrators to add categories.

### Financial information
- Bank name
- Account name
- Account number
- Branch
- Payment currency
- Payment terms
- Preferred payment method

Banking information must be encrypted where appropriate, masked in normal views, and accessible only to authorized staff.

### Compliance documentation
Allow authorized users to upload:
- Business registration documents
- Tax-related certificates, where applicable
- Company profile
- Contracts
- Service agreements
- Insurance certificates
- Other relevant compliance documents

Support document types, issue dates, expiry dates, and renewal reminders.

## 4.3 Vendor Lifecycle

Vendor statuses:
- Draft
- Pending review
- Approved
- Active
- Suspended
- Inactive
- Blacklisted
- Archived

Typical workflow:

Vendor registration → Verification → Administrative review → Approval → Activation.

Only authorized approvers can approve vendors.

High-risk changes, such as changes to vendor bank account details, must require independent secondary approval before taking effect.

Preserve both old and proposed values for review without unnecessarily exposing full bank details in notifications or logs.

## 4.4 Vendor Performance

Maintain:
- Service delivery history
- Contract compliance
- Quality rating
- Timeliness rating
- Responsiveness
- Reported issues
- Corrective actions
- Overall performance rating
- Evaluation date
- Evaluator

Support configurable vendor evaluation criteria and scoring.

## 4.5 Contract and Document Tracking

Record:
- Contract reference number
- Contract title
- Start date
- End date
- Contract amount
- Currency
- Responsible department
- Contract status
- Renewal requirements
- Uploaded agreement

Generate alerts before contract and document expiry.

## 4.6 Vendor Search and Reports

Allow filtering by:
- Name
- Category
- Location
- Approval status
- Registration period
- Compliance status
- Expiring contracts
- Performance rating

Reports:
- Vendor master list
- Active/inactive vendors
- Pending vendor approvals
- Vendors by category
- Expiring contracts
- Compliance expirations
- Vendor performance summary

Export authorized reports to CSV, Excel, or PDF.

---

# 5. MODULE TWO: DRIVER LOG AND VEHICLE MANAGEMENT

## 5.1 Objective

Develop a secure electronic logbook to monitor organizational vehicle movements, driver activities, fuel consumption, maintenance, roadworthiness, and vehicle utilization.

The module must support both desktop entry and mobile-friendly driver submissions.

## 5.2 Vehicle Registry

Capture:
- Unique vehicle ID
- Registration number
- Make
- Model
- Year of manufacture
- Vehicle type
- Fuel type
- Chassis/VIN number
- Engine number
- Assigned office
- Assigned department
- Current assigned driver, if applicable
- Acquisition date
- Acquisition cost, where authorized
- Current odometer reading
- Insurance policy reference
- Insurance expiry date
- Roadworthy certificate expiry
- Last service date
- Next scheduled service
- Vehicle condition
- Vehicle status

Statuses:
- Available
- Assigned
- In use
- Under maintenance
- Out of service
- Disposed

Registration numbers must be unique among active vehicle records.

## 5.3 Driver Registry

Capture:
- Driver ID
- Full name
- Employee ID
- Contact information
- Driver's licence class
- Licence expiry date
- Employment status
- Assigned vehicle(s)
- Driver authorization status

Restrict access to personally identifiable driver information.

Drivers with expired or invalid licence authorization must not be eligible for new trip assignments.

## 5.4 Trip and Movement Log

Every official vehicle trip must have a unique trip reference.

Required fields:
- Date
- Driver
- Vehicle
- Department
- Project/programme, where applicable
- Purpose of journey
- Requesting staff member
- Authorized by
- Departure location
- Destination
- Departure date and time
- Return date and time
- Opening odometer reading
- Closing odometer reading
- Total distance travelled
- Number of passengers, if required
- Fuel issued or purchased
- Remarks

Automatically calculate:

**Distance travelled = Closing odometer - Opening odometer**

Prevent:
- Negative distances
- Closing mileage below opening mileage
- Conflicting active vehicle assignments
- Overlapping trips involving the same vehicle or driver
- New opening mileage below the last validated reading without an approved correction
- Unauthorized modification of approved trip records

## 5.5 Trip Workflow

Implement:

1. Staff submits vehicle request, or an authorized officer creates a trip.
2. Administrative officer assigns a vehicle and driver.
3. Authorized manager approves the trip, where approval is required.
4. Driver records departure details.
5. Driver records arrival and trip completion details.
6. Administrative officer reviews the completed log.
7. Record is finalized and locked.

Trip statuses:
- Requested
- Pending approval
- Approved
- Assigned
- In progress
- Completed
- Under review
- Finalized
- Cancelled
- Rejected

Any correction after finalization must be submitted as a versioned amendment with a reason and independent approval.

## 5.6 Fuel Management

Track:
- Vehicle
- Driver
- Trip reference
- Fuel purchase date
- Fuel station/vendor
- Quantity in litres
- Unit cost
- Total cost
- Fuel type
- Odometer reading
- Receipt number
- Receipt attachment
- Recorded by
- Approved by

Calculate:
- Fuel consumption in litres
- Fuel cost per vehicle
- Fuel cost per kilometre
- Estimated kilometres per litre
- Monthly fuel expenditure
- Vehicle fuel efficiency trends

Where trip-level fuel allocation is unavailable, separate fuel-purchase records from estimated vehicle-level consumption rather than presenting an unsupported trip-level figure.

Flag unusual fuel consumption for review.

## 5.7 Vehicle Maintenance

Record:
- Maintenance ID
- Vehicle
- Maintenance type
- Description
- Date
- Service provider
- Cost
- Odometer at service
- Next service date or mileage
- Supporting invoice
- Maintenance status
- Authorized by

Support preventive and corrective maintenance.

Send reminders for upcoming servicing, insurance renewal, roadworthiness expiry, and other scheduled checks.

## 5.8 Accident and Incident Management

Record:
- Incident reference
- Incident date and time
- Vehicle
- Driver
- Location
- Incident description
- Severity
- Damage details
- Reported injuries, using strictly limited access
- Supporting evidence
- Police/insurance reference, where applicable
- Corrective action
- Incident status
- Review and closure details

Incident records must have stricter access controls than ordinary movement logs.

## 5.9 Driver and Vehicle Reports

Generate:
- Daily movement report
- Individual driver activity
- Vehicle utilization
- Mileage summary
- Fuel expenditure
- Fuel efficiency
- Maintenance expenditure
- Vehicle downtime
- Incident summary
- Upcoming insurance/roadworthiness renewals
- Vehicle activity by department or project

---

# 6. MODULE THREE: ASSET REGISTER MANAGEMENT

## 6.1 Objective

Develop a comprehensive asset management module covering acquisition, identification, assignment, location, maintenance, inventory verification, transfer, and disposal of organizational assets.

Support both ICT and non-ICT assets.

## 6.2 Asset Registration

Capture:

### Asset identity
- Unique asset ID
- Asset tag/code
- Asset name
- Description
- Category
- Subcategory
- Manufacturer
- Model
- Serial number
- Barcode/QR code

### Procurement information
- Purchase date
- Purchase cost
- Currency
- Purchase order reference
- Invoice reference
- Supplier/vendor
- Funding source, where applicable
- Project/programme
- Warranty start date
- Warranty expiry date

### Location and ownership
- Assigned office
- Department
- Physical location
- Custodian
- Assigned staff member
- Assignment date
- Ownership status

### Technical information
For ICT assets, allow:
- Device type
- Hostname
- Operating system
- Processor
- RAM
- Storage capacity
- Device identifier
- Security protection status
- Last physical verification date

Do not store device passwords, recovery keys, or other authentication secrets in ordinary asset records.

### Condition and status
Asset condition:
- New
- Excellent
- Good
- Fair
- Poor
- Damaged
- Unserviceable

Asset status:
- In stock
- Available
- Assigned
- Under maintenance
- Missing
- Lost
- Stolen
- Pending disposal
- Disposed

## 6.3 Asset Tagging

Automatically generate a unique asset tag.

Example format:

SEND-GH-ICT-2026-0001

Allow administrators to configure the organization's prefix, category codes, and tag numbering format.

Asset tag identifiers must remain unique and immutable once assigned.

Generate printable QR code/barcode labels.

Scanning a QR code should open an authenticated asset detail page without exposing confidential asset information to the public.

## 6.4 Asset Assignment

Allow administrators to assign assets to staff or departments.

Capture:
- Asset
- Recipient
- Assigning officer
- Assigned location
- Assignment date
- Condition on handover
- Accessories included
- Remarks
- Recipient acknowledgement
- Supporting documentation

Provide an electronic handover acknowledgement.

Staff should be able to view their assigned assets.

An asset must not have more than one active exclusive custodian assignment unless explicitly defined as shared equipment.

## 6.5 Asset Transfer

Implement:

Transfer request → Approval → Handover → Recipient acknowledgement → Register updated.

Record:
- Previous custodian
- New custodian
- Previous location
- New location
- Transfer reason
- Approval
- Date and time
- Condition at transfer
- Acknowledgement

Retain complete transfer history.

## 6.6 Asset Maintenance and Repairs

Record:
- Asset
- Reported fault
- Date reported
- Reported by
- Diagnosis
- Maintenance category
- Service provider
- Work performed
- Repair cost
- Repair date
- Return-to-service date
- Warranty claim details
- Supporting documents

Allow users to submit maintenance requests against assigned assets.

## 6.7 Asset Verification and Stocktaking

Develop an inventory verification workflow.

Features:
- Schedule physical verification
- Assign verification officers
- Scan QR codes to confirm assets
- Record actual locations
- Confirm custodians
- Record current conditions
- Identify missing assets
- Identify unregistered assets
- Reconcile discrepancies
- Generate verification reports

Each verification exercise must have a unique reference and final sign-off.

## 6.8 Asset Disposal

Implement a controlled disposal process.

Capture:
- Asset ID
- Reason for disposal
- Disposal method
- Condition
- Proposed disposal value
- Actual proceeds, if applicable
- Approval authority
- Supporting documentation
- Disposal date
- Disposal officer
- Final remarks

Disposal methods:
- Auction/sale
- Donation
- Recycling
- Destruction
- Write-off
- Other authorized method

Require an independent approval before final disposal.

Disposal must not delete the asset record.

## 6.9 Asset Depreciation

Optionally support:
- Straight-line depreciation
- Reducing-balance depreciation
- Configurable asset useful life
- Residual value
- Accumulated depreciation
- Net book value

Keep financial depreciation separate from physical asset condition.

Depreciation rules should be configurable and subject to Finance approval.

## 6.10 Asset Reports

Generate:
- Complete asset register
- Assets by category
- Assets by department
- Assets by location
- Assets assigned to individual staff
- Unassigned assets
- Asset acquisition reports
- Warranty expiry reports
- Maintenance and repair costs
- Asset transfer history
- Missing/lost assets
- Disposal reports
- Asset verification discrepancies
- Asset valuation and depreciation reports

---

# 7. INTEGRATION BETWEEN MODULES

The modules must not operate as completely isolated systems.

Examples:

**Vendor-to-asset integration**

When an asset is registered, the supplier should be selected from the approved vendor registry.

**Vendor-to-vehicle integration**

Vehicle maintenance and fuel purchases may be associated with approved vendors.

**Vehicle-to-asset integration**

Vehicles may be designated as a specialized asset category while maintaining vehicle-specific operational records.

Avoid duplicate asset and vehicle identities. Establish one authoritative vehicle record linked to its corresponding asset record where applicable.

**User-to-asset integration**

Asset custodians should reference staff/user records or a separate staff directory rather than using unrestricted free-text names.

**User-to-driver integration**

Driver records should be linked to employee identities or an authorized external-driver record.

**Cross-module audit integration**

All sensitive operations must be captured by a common, centralized audit service.

---

# 8. APPROVAL WORKFLOW ENGINE

Develop a reusable configurable approval engine.

The engine should support:
- Single-stage approval
- Multi-stage approval
- Sequential approval
- Configurable approval authorities
- Request status tracking
- Approval comments
- Rejection reasons
- Escalation rules
- Approval notifications
- Delegated approval with time limits
- Complete approval history

Apply maker-checker separation of duties.

A person must not approve their own critical transaction.

Examples requiring approvals:
- New vendor activation
- Vendor bank detail changes
- Vendor suspension/reactivation
- High-value asset procurement records, where required
- Asset transfers
- Asset disposal
- Corrections to finalized driver logs
- Exceptional fuel transactions

Approvals must be associated with the specific data version reviewed.

If sensitive information changes after approval, invalidate the earlier approval and require a new review.

The engine should be reusable by future modules.

---

# 9. AUDIT LOGGING AND ACCOUNTABILITY

Implement comprehensive audit logging.

Every meaningful system action must be attributable to an authenticated user or identified system process.

## 9.1 Events to Audit

Record:
- Successful logins
- Failed login attempts
- Logouts
- Password resets
- MFA changes
- User account creation
- User suspension/reactivation
- Role changes
- Permission changes
- Record creation
- Record updates
- Record archival/deletion
- Approval and rejection actions
- Data exports
- Sensitive document access
- Asset assignment/transfer/disposal
- Vendor financial-information changes
- Driver-log corrections
- Configuration changes
- Backup and restore operations

## 9.2 Audit Event Structure

Each event must contain:
- Unique event ID
- Timestamp in UTC
- Actor/user ID
- Action
- Affected module
- Affected record identifier
- Event outcome
- Source IP address
- User agent, where relevant
- Correlation/request ID
- Previous and new values where appropriate
- Reason for sensitive modifications
- Approval reference when applicable

Never log plaintext passwords, authentication tokens, recovery keys, or unnecessarily complete banking information.

## 9.3 Audit Security

Requirements:
- Ordinary users cannot modify audit logs.
- Application administrators cannot silently alter audit history.
- Centralized protected storage for security-critical events.
- Tamper-evident audit records.
- Restrict access to authorized reviewers.
- Monitor suspicious activity.
- Retain logs according to a documented retention policy.

Implement append-only application audit records backed by independently protected log storage or equivalent tamper-evident controls.

Do not rely only on database records editable by a privileged application administrator.

## 9.4 Audit Interface

Provide an Audit Trail dashboard with filters for:
- User
- Module
- Action
- Date range
- Record ID
- IP address
- Event outcome

Allow authorized auditors to inspect record change histories and export redacted audit reports.

---

# 10. INFORMATION SECURITY REQUIREMENTS

Security must be designed around the three pillars of the CIA Triad:

**Confidentiality, Integrity, and Availability.**

## 10.1 Confidentiality

Implement:
- HTTPS everywhere
- Strong authentication
- MFA for privileged users
- RBAC and record-level authorization
- Least-privilege access
- Server-side permission enforcement
- Encryption at rest for sensitive data
- Secure storage of uploaded documents
- No public exposure of confidential files
- Signed, short-lived download links where appropriate
- Sensitive-field masking
- Secure password hashing
- CSRF protection
- Secure session cookies
- Session timeout and revocation
- Rate limiting
- Protection against unauthorized data exports

Users must only access records necessary for their responsibilities.

## 10.2 Integrity

Implement:
- Database constraints
- Server-side validation
- Protection against SQL injection
- Protection against cross-site scripting
- Consistent transactional updates
- Record versioning
- Optimistic locking for concurrent updates
- Maker-checker approvals
- Tamper-evident logs
- File integrity checks where appropriate
- Controlled amendments to finalized records
- Data reconciliation checks
- Cryptographic checksums for important stored documents
- Referential integrity across related modules

Important finalized or approved records must never be overwritten without preserving their change history.

## 10.3 Availability

Implement:
- Automated backups
- Encrypted offsite backup copies
- Backup monitoring
- Periodic restoration tests
- Application health checks
- Centralized error monitoring
- Database performance monitoring
- Disaster recovery procedures
- Recovery Time Objective (RTO)
- Recovery Point Objective (RPO)
- Storage capacity monitoring
- Background processing for large exports
- Graceful error handling
- Secure maintenance mode
- Documented operational recovery procedures

Backup schedules, RTO, and RPO must be configurable based on organizational operational requirements.

A successful backup process is not sufficient without a verified recovery capability.

## 10.4 Application Security

Use OWASP-aligned secure development practices.

Requirements:
- No hard-coded credentials
- Environment-based secrets
- No secrets committed to source control
- Input sanitization and contextual output encoding
- Server-side validation
- Parameterized database queries
- Strict file-upload validation
- Upload file size/type restrictions
- Malware scanning of uploaded files where supported
- Dependency vulnerability checks
- Secure HTTP headers
- Appropriate Content Security Policy
- Authorization tests for every sensitive operation
- Generic authentication error messages
- Safe error handling with no stack traces exposed publicly
- Protection against insecure direct object references
- Controls against mass assignment
- Safe background job permissions
- Regular security patching

Follow secure-by-default principles.

---

# 11. DOCUMENT MANAGEMENT

Create a shared secure document management component for all modules.

Support:
- Uploading documents
- File categorization
- Document versions
- Linked module records
- Uploaded by
- Upload date
- File type
- File size
- Document expiry
- Document access restrictions
- Secure download
- Archival

Store documents outside publicly accessible directories or use protected private object storage.

Users must never access files by guessing storage paths.

Document authorization must be checked at every download or preview request.

---

# 12. DASHBOARD AND USER INTERFACE

Build a clean, modern, responsive dashboard using SEND Ghana branding.

## 12.1 Main Dashboard

Include cards for:
- Total vendors
- Active vendors
- Pending vendor approvals
- Total vehicles
- Vehicles currently in use
- Vehicles due for servicing
- Trips awaiting review
- Total registered assets
- Assets currently assigned
- Unassigned assets
- Assets pending disposal
- Pending approvals
- Recent administrative activity

Include graphical summaries:
- Vendor distribution by category
- Monthly vehicle mileage
- Fuel expenditure trends
- Asset distribution by category
- Asset distribution by department
- Maintenance expenditure
- Monthly administrative activity

Dashboards must show only metrics the logged-in user is authorized to access.

## 12.2 Navigation

Suggested sidebar:
- Dashboard
- Vendor Management
- Vehicles & Driver Logs
- Asset Register
- Approvals
- Reports & Analytics
- Notifications
- Audit Trail
- User Management
- System Settings
- Help & Documentation

Navigation must adapt to the user's permissions.

## 12.3 Usability

Provide:
- Responsive layouts
- Searchable tables
- Pagination
- Sorting and filtering
- Clear form validation
- Confirmation dialogs for sensitive actions
- Status labels
- Mobile-friendly driver forms
- Breadcrumbs
- Accessible forms and keyboard navigation
- Empty-state instructions
- Consistent date and currency presentation

Use Africa/Accra as the default presentation timezone while storing timestamps in UTC.

Use GHS as the default currency, with support for other currencies.

---

# 13. REPORTING AND ANALYTICS

Develop a centralized reporting engine.

Features:
- Date range filters
- Department filters
- Location filters
- Status filters
- Configurable columns
- CSV export
- Excel export
- PDF export
- Print-friendly reports
- Report generation history
- Access-controlled downloads

Reports must be restricted by user permissions.

Export actions must be logged.

Where feasible, provide scheduled report generation through background jobs.

---

# 14. NOTIFICATIONS AND REMINDERS

Create an application notification center.

Supported events:
- Vendor approval requests
- Vendor document expiry
- Contract expiry
- Trip approvals
- Overdue/unclosed trips
- Vehicle insurance expiry
- Roadworthiness expiry
- Scheduled maintenance
- Asset handover awaiting acknowledgement
- Pending asset transfer
- Asset warranty expiry
- Asset verification deadlines
- Approval escalations
- Important security alerts

Notification channels:
- In-app notifications
- Email notifications

Allow administrators to configure notification lead times.

Avoid exposing sensitive details in email subject lines or notification previews.

---

# 15. DATABASE ARCHITECTURE

Design a normalized relational schema with appropriate indexes, foreign keys, uniqueness constraints, and transaction boundaries.

Suggested primary entities:

### Identity and access
- users
- staff_profiles
- departments
- office_locations
- roles
- permissions
- user_roles
- role_permissions
- user_sessions
- authentication_events

### Vendor management
- vendors
- vendor_categories
- vendor_contacts
- vendor_bank_accounts
- vendor_documents
- vendor_contracts
- vendor_evaluations
- vendor_status_history

### Vehicle management
- vehicles
- drivers
- trip_requests
- vehicle_assignments
- driver_logs
- fuel_records
- maintenance_records
- vehicle_incidents
- vehicle_documents
- odometer_adjustments

### Asset management
- assets
- asset_categories
- asset_assignments
- asset_transfers
- asset_maintenance
- asset_verifications
- asset_verification_items
- asset_disposals
- asset_depreciation_records
- asset_documents

### Shared services
- approval_requests
- approval_steps
- approval_decisions
- notifications
- audit_events
- document_versions
- report_exports
- application_settings
- system_health_events
- backup_job_records

Each operational record should contain appropriate metadata such as:
- id
- created_at
- updated_at
- created_by
- updated_by
- status
- version

Use soft deletion or controlled archival only where appropriate; it must not replace audit history.

Use unique constraints and transactional rules to prevent duplicated or conflicting records.

Avoid duplicate data and create reusable shared services.

---

# 16. SEARCH, FILTERING, AND DATA QUALITY

Provide advanced searching across authorized records.

Features:
- Keyword search
- Filter combinations
- Sortable columns
- Duplicate detection
- Validation before saving
- Required-field indicators
- Data completeness indicators
- Controlled imports

Where imports are supported:
- Accept CSV/Excel
- Validate each row
- Preview results
- Identify duplicates
- Display errors before import
- Record import history
- Ensure imports respect approval and audit requirements

Imported data must not bypass standard validation or authorization.

---

# 17. SYSTEM ADMINISTRATION

Build a secure administration console for:

- User management
- Role and permission management
- Department management
- Office/location management
- Vendor category management
- Asset category management
- Approval workflow configuration
- Notification configuration
- Report configuration
- System settings
- Audit monitoring
- System health monitoring
- Backup status monitoring

Restrict technical configuration to properly authorized accounts.

Security-critical changes should require reauthentication and/or independent authorization based on risk.

---

# 18. TESTING AND QUALITY ASSURANCE

Implement automated tests.

### Functional testing
- User authentication
- Permission enforcement
- Vendor creation and approval
- Driver log creation and finalization
- Fuel calculations
- Asset assignments
- Asset transfers
- Asset disposal
- Notifications
- Report generation

### Security testing
- Unauthorized record access
- Privilege escalation attempts
- Horizontal access-control bypasses
- CSRF protection
- XSS protection
- SQL injection resistance
- File-upload restrictions
- Session security
- Failed-login throttling
- Audit log integrity
- Sensitive data exposure
- Export authorization

### Data integrity testing
- Duplicate asset tags
- Conflicting vehicle reservations
- Overlapping driver assignments
- Incorrect odometer readings
- Duplicate active asset assignments
- Approval bypass attempts
- Concurrent record updates
- Invalid asset lifecycle transitions

### Availability and recovery testing
- Backup restoration
- Failed background jobs
- Interrupted transactions
- Database recovery
- Storage unavailability
- Large-volume reporting

Do not consider the application production-ready until all critical security and integrity tests pass.

---

# 19. DEPLOYMENT AND DOCUMENTATION

Provide:

1. Complete source code
2. Database migrations
3. Database seeders
4. Initial role and permission setup
5. Development environment configuration
6. Production deployment instructions
7. Web server configuration guidance
8. Secure environment variable template
9. Queue worker configuration
10. Task scheduling setup
11. Backup and restoration instructions
12. Administrator user guide
13. End-user guide
14. API documentation where applicable
15. System architecture documentation
16. Database entity relationship diagram
17. Security architecture documentation
18. Testing documentation
19. Disaster recovery guidance

Ensure compatibility with a Linux web hosting environment running supported PHP and MySQL versions.

Where shared hosting is used, identify limitations that could affect queues, scheduled jobs, centralized logs, or other security and availability controls.

Do not silently remove important controls to accommodate a restrictive hosting environment.

---

# 20. PHASED DEVELOPMENT PLAN

Build the system incrementally.

## Phase 1: Foundation
- Project structure
- Database architecture
- Authentication
- Role-based authorization
- User management
- Shared document management
- Audit logging
- Reusable approval engine
- Basic dashboard

## Phase 2: Vendor Records
- Vendor registration
- Vendor verification
- Approvals
- Contract tracking
- Vendor document management
- Performance evaluations
- Reports

## Phase 3: Vehicle and Driver Management
- Vehicle registry
- Driver profiles
- Vehicle requests
- Trip logs
- Fuel records
- Maintenance schedules
- Incident reporting
- Reports

## Phase 4: Asset Register
- Asset registration
- Asset tagging
- Assignment
- Transfers
- Maintenance
- Physical verification
- Disposal
- Reporting

## Phase 5: Security and Operational Hardening
- Security testing
- Access-control testing
- Audit integrity verification
- Backup and restoration testing
- Monitoring and alerts
- Performance optimization
- Production deployment readiness review

Security controls must be implemented throughout all development phases, not postponed until Phase 5.

---

# 21. FUTURE EXPANSION

Design the architecture to accommodate additional modules such as:
- Procurement management
- Inventory and consumables
- Administrative requisitions
- Contract management
- Facilities maintenance
- Meeting room and equipment bookings
- Travel requests
- Staff logistics
- Administrative budgeting

Do not implement these additional modules in the initial release unless explicitly requested.

---

# 22. DEVELOPMENT AND DELIVERY INSTRUCTIONS

Follow these instructions strictly.

1. Begin with a detailed system requirements specification.
2. Identify actors, permissions, business rules, and data relationships.
3. Produce an architecture diagram and database ERD before implementation.
4. Present the recommended Laravel project directory structure.
5. Identify security-sensitive operations and their protection mechanisms.
6. Define explicit acceptance criteria for each module.
7. Design reusable authentication, audit, notification, file management, and approval components.
8. Implement the application in phases using maintainable, well-structured code.
9. Write migrations, models, policies, requests, controllers, services, views, and automated tests.
10. Apply authorization both at the interface and backend level.
11. Ensure every sensitive action has an auditable event.
12. Never use insecure shortcuts such as hard-coded passwords or unrestricted file downloads.
13. Provide sample non-sensitive seed data for testing.
14. Validate that all workflows function correctly before moving to the next phase.
15. Document environment-specific assumptions and configuration requirements.
16. Provide clear instructions for running, testing, updating, and deploying the application.
17. Do not claim a feature is complete until its implementation and relevant tests are included.
18. Explicitly identify any remaining risks, unimplemented integrations, or deployment prerequisites.

### Initial Deliverables

Before generating application code, produce:

**Deliverable A:** Full Software Requirements Specification (SRS).

**Deliverable B:** System Architecture, including application components and security boundaries.

**Deliverable C:** Database ERD and relationship descriptions.

**Deliverable D:** Role-Permission Matrix.

**Deliverable E:** Workflows for vendor approval, vehicle movement, asset assignment, asset transfer, and asset disposal.

**Deliverable F:** Security Control Matrix organized under Confidentiality, Integrity, and Availability.

**Deliverable G:** Phased Implementation Plan with measurable acceptance criteria.

After these deliverables have been developed, proceed with Phase 1 implementation.

**Ultimate goal:** Build a reliable, secure, auditable, user-friendly, and scalable administrative platform that SEND Ghana can confidently use for its everyday organizational operations.