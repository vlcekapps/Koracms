# Kora CMS Accessibility Conformance Report Draft

Stav: interní draft podle VPAT 2.5Rev WCAG edition. Tento dokument není právní certifikace a má být aktualizován po ručním testování.

## Product Information

- Product: Kora CMS
- Version evaluated: current development branch
- Report type: WCAG 2.2 A/AA product accessibility conformance draft
- Report date: 2026-07-03
- Evaluation methods: source inspection, automated guardrails, runtime audits, HTTP integration scenarios, theme view audit, manual protocol prepared in `manual-test-protocol.md`
- Standards: WCAG 2.2 Level A and AA
- Source references: W3C WCAG 2 Overview, W3C WCAG 2.2 Quick Reference, W3C WCAG-EM and ITI VPAT 2.5Rev WCAG edition.

## Conformance Levels Used

- Supports: functionality meets the criterion without known defects in the evaluated CMS-provided experience.
- Partially Supports: some functionality supports the criterion, but manual verification, author-supplied content, or known gaps remain.
- Does Not Support: the product does not currently meet the criterion.
- Not Applicable: the criterion does not apply to CMS-generated functionality.

## Notes

Kora CMS is an authoring and publishing system. This ACR separates CMS responsibility from author responsibility. The CMS can provide accessible UI, fields, validation, metadata, and safe defaults; it cannot guarantee that every manually authored HTML block, uploaded media file, transcript, video caption, alt text, or external embed is accessible.

## WCAG 2.2 Level A

| Criteria | Conformance | Remarks and Explanations |
|---|---|---|
| 1.1.1 Non-text Content | Supports | CMS provides alt text fields, decorative-image support and theme audit coverage. Author-supplied media still needs editorial review. |
| 1.2.1 Audio-only and Video-only | Partially Supports | CMS supports audio/video publication, but transcripts and alternatives are supplied by authors. |
| 1.2.2 Captions (Prerecorded) | Partially Supports | Video embed support exists; captions depend on uploaded/external media. |
| 1.2.3 Audio Description or Media Alternative | Partially Supports | Authors can provide text alternatives in content; dedicated audio-description workflow is not yet modeled. |
| 1.3.1 Info and Relationships | Supports | Forms, landmarks, tables and sections are covered by runtime and theme guardrails. |
| 1.3.2 Meaningful Sequence | Supports | Shared layouts preserve meaningful DOM sequence; mobile order still needs manual regression testing. |
| 1.3.3 Sensory Characteristics | Supports | Instructions are text-based and do not rely only on color, shape or position. |
| 1.4.1 Use of Color | Supports | Statuses and labels include text, not color alone. |
| 1.4.2 Audio Control | Not Applicable | Core CMS does not autoplay audio. |
| 2.1.1 Keyboard | Partially Supports | Core patterns support keyboard use; command center, widget dialog and content/media picker have automated guardrails for Escape, Tab focus cycling and focus return, with manual no-regression confirmation on 2026-07-04. Dense admin workflows still need manual coverage. |
| 2.1.2 No Keyboard Trap | Supports | Core dialogs are designed with focus trap and focus return; command center, widget dialog and content/media picker were manually confirmed without regression on 2026-07-04. |
| 2.1.4 Character Key Shortcuts | Supports | Global command shortcut uses `Ctrl+K` and is disabled in form fields. |
| 2.2.1 Timing Adjustable | Partially Supports | Content lock refresh reduces data loss; auth/session timeout behavior needs manual assessment. |
| 2.2.2 Pause, Stop, Hide | Supports | Core does not generate moving or auto-updating visual content requiring pause controls. |
| 2.3.1 Three Flashes or Below Threshold | Supports | Core does not generate flashing content. |
| 2.4.1 Bypass Blocks | Supports | Skip links and main content anchors are present in public/admin/system layouts. |
| 2.4.2 Page Titled | Supports | Shared SEO/theme layers generate page titles. |
| 2.4.3 Focus Order | Partially Supports | Layouts and core dialogs are structured for logical focus, with automated guardrails for returning focus to the trigger in command center, widget dialog and content/media picker. Those dialogs were manually confirmed without regression on 2026-07-04; broader admin keyboard sweeps remain required. |
| 2.4.4 Link Purpose (In Context) | Supports | Links generally use meaningful text and hidden contextual suffixes where needed. |
| 2.5.1 Pointer Gestures | Supports | Core does not require multipoint or path-based gestures. |
| 2.5.2 Pointer Cancellation | Supports | State-changing actions are explicit buttons/POST actions, not down-event-only actions. |
| 2.5.3 Label in Name | Partially Supports | The product avoids replacing visible text with unrelated ARIA labels; icon-only and dynamic controls need manual checks. |
| 2.5.4 Motion Actuation | Not Applicable | Core CMS does not use device motion controls. |
| 3.1.1 Language of Page | Supports | CMS pages use Czech page language in shared layouts. |
| 3.2.1 On Focus | Supports | Focus does not intentionally trigger unexpected context changes. |
| 3.2.2 On Input | Supports | Inputs generally require explicit submit/action for context changes. |
| 3.2.6 Consistent Help | Partially Supports | Help text and contact options exist; repeated help mechanisms need an inventory pass. |
| 3.3.1 Error Identification | Supports | Field-level and form-level error messaging is guarded. |
| 3.3.2 Labels or Instructions | Supports | Forms use labels, fieldsets, legends and helper text. |
| 3.3.7 Redundant Entry | Partially Supports | Some data is reused, but redundant-entry coverage needs workflow testing. |
| 4.1.2 Name, Role, Value | Supports | Controls, dialogs and landmarks are covered by ARIA/name guardrails, including command center `aria-expanded` state, rendered dialog semantics and manual no-regression confirmation for core dialogs on 2026-07-04. |

## WCAG 2.2 Level AA

| Criteria | Conformance | Remarks and Explanations |
|---|---|---|
| 1.2.4 Captions (Live) | Not Applicable | Core CMS does not provide live audio/video streaming. |
| 1.2.5 Audio Description (Prerecorded) | Partially Supports | Authors can provide descriptions, but there is no dedicated metadata workflow for audio description. |
| 1.3.4 Orientation | Supports | CMS does not restrict screen orientation. |
| 1.3.5 Identify Input Purpose | Partially Supports | Authentication fields are covered for `username`, `current-password`, `new-password` and 2FA `one-time-code` metadata. Public contact, food order, guest reservation and generated Form Builder forms now expose guarded autocomplete metadata for name, email, phone, URL and obvious organization/name text fields. Browser autofill behavior and specialized address/payment custom fields still need manual verification. |
| 1.4.3 Contrast (Minimum) | Partially Supports | Runtime `contrast_focus_guardrails` now measures default theme, admin layout and standalone login text/status color pairs against AA thresholds. Custom theme settings, hover/disabled states and author-supplied colors still need manual measurement. |
| 1.4.4 Resize Text | Supports | Layouts are responsive and do not block browser zoom. |
| 1.4.5 Images of Text | Supports | Core UI does not use images of text as controls or headings. |
| 1.4.10 Reflow | Partially Supports | Runtime `admin_mobile_reflow_guardrails` covers the shared admin mobile baseline. Browser verification on 2026-07-04 at 320 px covered media, widgets, statistics, Form Builder, forms overview, comments, contact, chat, reservations, food, downloads, gallery, import screens, the content picker and representative long edit forms for page/blog/news/event/download/gallery/food/board/FAQ/place/polls/reservations/podcast. Dense admin tables, podcast overviews and reservation opening-hours tables use local responsive wrappers; fieldsets and form controls shrink inside the viewport. Broader 400 % coverage for custom modules and assistive technology combinations is still required. |
| 1.4.11 Non-text Contrast | Partially Supports | Runtime guardrails measure focus indicators plus default/admin/login input and button borders against the 3:1 non-text threshold. Icons, progress bars, disabled/hover states and custom theme variants still need manual verification. |
| 1.4.12 Text Spacing | Partially Supports | Runtime `text_spacing_guardrails` checks core CSS for negative letter spacing, text ellipsis, line clamp and `!important` locks on text-spacing properties; admin SEO preview now wraps long title/description text instead of clipping it. Browser verification with a text-spacing override is still required. |
| 1.4.13 Content on Hover or Focus | Partially Supports | Core avoids hover-only content; dropdowns/tooltips require manual checks. |
| 2.4.5 Multiple Ways | Supports | Navigation, search, sitemap and module listings provide multiple discovery paths. |
| 2.4.6 Headings and Labels | Supports | Heading-backed landmarks and form labels are guarded. |
| 2.4.7 Focus Visible | Supports | Public, admin and standalone login CSS include visible focus styles, and runtime guardrails measure focus token contrast against primary backgrounds. |
| 2.4.11 Focus Not Obscured (Minimum) | Partially Supports | Admin mobile CSS removes the fixed side-by-side sidebar/content layout at narrow widths, keeps command and content picker dialogs within the viewport and was browser-verified with visible focus in the mobile admin search at 320 px. Long edit forms were also verified at 320 px without hidden main-content horizontal scroll. Sticky/anchor interactions and 400 % keyboard/screen-reader combinations still need manual verification. |
| 2.5.7 Dragging Movements | Supports | Reordering uses accessible button alternatives and is not drag-only. |
| 2.5.8 Target Size (Minimum) | Partially Supports | Shared admin CSS guards mobile navigation targets, flexible action rows, secondary links in action rows, direct paragraph action links and minimum sort-control target sizing. Browser verification at 320 px found no checked standalone controls below 24 px in media/widgets/statistics/Form Builder/forms overview/contact/chat/food/downloads/gallery/content picker and representative long edit forms; inline text-link exceptions, icon-like controls and custom module actions still need manual measurement. |
| 3.1.2 Language of Parts | Partially Supports | Authors can add HTML language attributes, but CMS does not enforce them. |
| 3.2.3 Consistent Navigation | Supports | Shared public/admin layouts keep navigation consistent. |
| 3.2.4 Consistent Identification | Supports | Shared module manifests and labels support consistent naming. |
| 3.3.3 Error Suggestion | Partially Supports | Public math verification errors in contact, newsletter, board subscription, Food orders and Form Builder use a shared actionable suggestion and are covered by runtime/HTTP guardrails. Selected admin URL errors for places and podcasts explain accepted http/https or domain-only input and the empty optional-field fallback. Selected admin email errors now suggest a complete `jmeno@example.cz`-style address, leaving optional fields empty or keeping login email addresses unique. Selected admin date errors for board items, food menus and downloads now suggest choosing a calendar date, leaving optional fields empty or correcting from/to ordering. Download source, external URL, project URL, upload file and SHA-256 checksum errors now provide field-level suggestions for local file versus external URL, http/https or domain-only input, optional empty project URL and exact 64-character checksums. Selected admin image upload errors for articles, board items, events, places and downloads now suggest JPEG/PNG/GIF/WebP, explain that SVG and other formats are rejected, and preserve optional empty image fields; download preview image errors now use field-level feedback. Selected scheduling date/time errors for articles, pages, news, events, polls, reservation resources and podcast episodes now suggest choosing a valid date/time value, leaving optional fields empty, removing empty rows or correcting start/end ordering. A full validation-copy review for remaining admin, custom and less common errors is still pending. |
| 3.3.4 Error Prevention (Legal, Financial, Data) | Partially Supports | CSRF, confirmations, PRG and audit logs exist; critical workflows need mapping. |
| 3.3.8 Accessible Authentication (Minimum) | Supports | Registration and password reset request do not require a math CAPTCHA; they use CSRF, rate limiting and honeypot protection. Runtime and HTTP integration guardrails cover no-CAPTCHA auth rendering, password-manager autocomplete metadata, 2FA one-time-code/numeric metadata and text-backed admin/2FA error alerts. Manual password-manager, TOTP, token reset and error-state behavior was confirmed on 2026-07-04. |
| 4.1.3 Status Messages | Supports | Status and error messages use live region semantics and guarded roles. |

## Current Summary

- Supports: strong structural support for landmarks, forms, status messages, navigation, page titles and semantic UI.
- Partially Supports: media alternatives, contrast measurement, keyboard/focus manual sweeps, target size, timeouts and redundant entry.
- Does Not Support: no criteria are currently classified this way in the draft baseline.
- Not Applicable: live media, autoplay audio and motion actuation are not generated by core CMS.

The next revision should update statuses only after executing `manual-test-protocol.md`.
