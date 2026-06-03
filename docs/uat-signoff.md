# ICT UAT Sign-off Sheet

## UAT Session Info

- Environment:
- Date:
- Facilitator:
- ICT reviewers:

## Scenario Checklist

- [ ] Microsoft sign-in works for teacher/admin/ICT
- [ ] Teacher can create, save, and close attendance session
- [ ] Admin can view attendance summaries and trends
- [ ] Reports filters and exports respond correctly
- [ ] Student history view is correct
- [ ] Dynamics student sync works and does not duplicate students
- [ ] Role restrictions are enforced (no unauthorized admin/report access)

## Defects / Feedback Log

| ID | Scenario | Severity | Description | Owner | Status |
| --- | --- | --- | --- | --- | --- |
| UAT-001 | Dynamics authentication | High | Dynamics tenant/client credentials must be verified in UAT/Prod to prevent empty sync payloads. | ICT | Open |
| UAT-002 | Multi-role access | Medium | Run role matrix validation with real school users and confirm least-privilege behavior in reports/admin pages. | QA + ICT | Open |
| UAT-003 | Attendance close/sync flow | Medium | Verify close-session and Dynamics sync queue behavior under classroom load. | QA | Open |

## Sign-off

- ICT Lead Name:
- Decision: `Approved` / `Approved with conditions` / `Rejected`
- Comments:
