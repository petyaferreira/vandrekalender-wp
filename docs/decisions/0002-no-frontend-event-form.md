# 0002: No frontend event form; organisers use the WordPress editor

Decided 14 October 2026 with Petya ([#48](https://github.com/petyaferreira/vandrekalender-wp/issues/48)).

## Decision

There is no form on the public site for creating events. Organisers log in and create and edit events in the WordPress editor, with the event fields in the editor sidebar (`resources/event-meta-fields/`). The Event Author role sees only the Events menu in wp-admin ([authentication.md](../authentication.md)).

## Why

Dropped when the first build plan was archived (#48): the plan's Phase 4 had "logged-in users can create events via frontend form", and the WordPress editor already lets organisers create events.

## Do not propose

A frontend submission block, form plugin, or "submit a walk" page that creates `event` posts.

## Revisit when

Organisers report that the WordPress editor is a barrier to adding walks.
