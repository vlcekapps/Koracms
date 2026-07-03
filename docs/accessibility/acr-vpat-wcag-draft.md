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
| 2.1.1 Keyboard | Partially Supports | Core patterns support keyboard use; all JS dialogs and dense admin workflows need manual coverage. |
| 2.1.2 No Keyboard Trap | Supports | Dialogs are designed with focus trap and focus return. |
| 2.1.4 Character Key Shortcuts | Supports | Global command shortcut uses `Ctrl+K` and is disabled in form fields. |
| 2.2.1 Timing Adjustable | Partially Supports | Content lock refresh reduces data loss; auth/session timeout behavior needs manual assessment. |
| 2.2.2 Pause, Stop, Hide | Supports | Core does not generate moving or auto-updating visual content requiring pause controls. |
| 2.3.1 Three Flashes or Below Threshold | Supports | Core does not generate flashing content. |
| 2.4.1 Bypass Blocks | Supports | Skip links and main content anchors are present in public/admin/system layouts. |
| 2.4.2 Page Titled | Supports | Shared SEO/theme layers generate page titles. |
| 2.4.3 Focus Order | Partially Supports | Layouts and dialogs are structured for logical focus; manual keyboard sweeps remain required. |
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
| 4.1.2 Name, Role, Value | Supports | Controls, dialogs and landmarks are covered by ARIA/name guardrails. |

## WCAG 2.2 Level AA

| Criteria | Conformance | Remarks and Explanations |
|---|---|---|
| 1.2.4 Captions (Live) | Not Applicable | Core CMS does not provide live audio/video streaming. |
| 1.2.5 Audio Description (Prerecorded) | Partially Supports | Authors can provide descriptions, but there is no dedicated metadata workflow for audio description. |
| 1.3.4 Orientation | Supports | CMS does not restrict screen orientation. |
| 1.3.5 Identify Input Purpose | Partially Supports | Authentication fields are now covered for `username`, `current-password`, `new-password` and 2FA `one-time-code` metadata by runtime and HTTP integration guardrails. Contact, ordering, reservations and generated form templates still need a broader autocomplete audit. |
| 1.4.3 Contrast (Minimum) | Partially Supports | Default UI targets AA; custom themes and all states need manual measurement. |
| 1.4.4 Resize Text | Supports | Layouts are responsive and do not block browser zoom. |
| 1.4.5 Images of Text | Supports | Core UI does not use images of text as controls or headings. |
| 1.4.10 Reflow | Partially Supports | Responsive patterns exist; 320 px and admin tables need manual verification. |
| 1.4.11 Non-text Contrast | Partially Supports | Focus and controls are styled; detailed color measurement remains required. |
| 1.4.12 Text Spacing | Partially Supports | No known blocking CSS, but text spacing must be manually tested. |
| 1.4.13 Content on Hover or Focus | Partially Supports | Core avoids hover-only content; dropdowns/tooltips require manual checks. |
| 2.4.5 Multiple Ways | Supports | Navigation, search, sitemap and module listings provide multiple discovery paths. |
| 2.4.6 Headings and Labels | Supports | Heading-backed landmarks and form labels are guarded. |
| 2.4.7 Focus Visible | Supports | Public/admin CSS includes visible focus styles. |
| 2.4.11 Focus Not Obscured (Minimum) | Partially Supports | Sticky and anchor interactions need manual verification. |
| 2.5.7 Dragging Movements | Supports | Reordering uses accessible button alternatives and is not drag-only. |
| 2.5.8 Target Size (Minimum) | Partially Supports | Public controls are generally large enough; compact admin row actions need measurement. |
| 3.1.2 Language of Parts | Partially Supports | Authors can add HTML language attributes, but CMS does not enforce them. |
| 3.2.3 Consistent Navigation | Supports | Shared public/admin layouts keep navigation consistent. |
| 3.2.4 Consistent Identification | Supports | Shared module manifests and labels support consistent naming. |
| 3.3.3 Error Suggestion | Partially Supports | Many errors are specific; all validation copy needs review. |
| 3.3.4 Error Prevention (Legal, Financial, Data) | Partially Supports | CSRF, confirmations, PRG and audit logs exist; critical workflows need mapping. |
| 3.3.8 Accessible Authentication (Minimum) | Partially Supports | Registration and password reset request no longer require a math CAPTCHA; they use CSRF, rate limiting and honeypot protection. Runtime and HTTP integration guardrails cover no-CAPTCHA auth rendering, password-manager autocomplete metadata, 2FA one-time-code/numeric metadata and text-backed admin/2FA error alerts. End-to-end password-manager, TOTP and screen-reader behavior still need manual verification. |
| 4.1.3 Status Messages | Supports | Status and error messages use live region semantics and guarded roles. |

## Current Summary

- Supports: strong structural support for landmarks, forms, status messages, navigation, page titles and semantic UI.
- Partially Supports: media alternatives, contrast measurement, keyboard/focus manual sweeps, target size, timeouts, redundant entry and accessible authentication.
- Does Not Support: no criteria are currently classified this way in the draft baseline.
- Not Applicable: live media, autoplay audio and motion actuation are not generated by core CMS.

The next revision should update statuses only after executing `manual-test-protocol.md`.
