# Handover Deck Outline

## Slide 1: Project Overview

- Roll Call and Student Tracking System goals
- Scope: attendance, student tracking, reports, admin oversight

## Slide 2: Architecture

- Laravel API + Next.js frontend
- Auth via Microsoft Entra
- Dynamics integration + sync ledger

## Slide 3: Core Workflows

- Teacher roll call flow
- Admin analytics/reporting flow
- Student history lookup flow

## Slide 4: Security and Roles

- JWT authentication
- Role-based API restrictions (`teacher`, `admin`, `ict_staff`)
- Tenant school context

## Slide 5: Quality and Testing

- Backend feature test coverage for auth/attendance/reports/roles
- Frontend integration tests for key workflows
- CI pipeline checks

## Slide 6: Deployment

- Environment requirements
- Backend/frontend deployment model
- Queue worker and operational notes

## Slide 7: Known Risks / Open Items

- Final ICT UAT sign-off pending
- Production Dynamics credentials and environment validation

## Slide 8: Handover Items

- User guide
- API reference
- ERD + data mapping
- UAT sign-off sheet
