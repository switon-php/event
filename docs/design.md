# Design Rationale {#design-rationale}

`switon/event` keeps in-process reactions small, explicit, and separate from transport or monitoring pipelines.

## See also {#see-also}

- [Event Package](README.md) — usage, configuration, and the main user path
- [FAQ](faq.md#event-faq) — common questions and troubleshooting

---

## Core Problem {#core-problem}

Applications usually drift into three different reaction styles:

- one side effect stays inline in the service
- another becomes a listener hook
- another is pushed toward async transport

Switon event exists to keep one clear in-process reaction boundary before queue, RPC, or workflow concerns enter the
picture.

## What It Standardizes {#what-it-standardizes}

- class-based events for identity and payload
- `#[EventListener]` for listener registration close to the handler
- synchronous dispatch inside the current process
- `object` listeners for cross-cutting observation
- `ObservabilityProbe` for logging, tracing, and metrics listeners
- `StopFlow` for intentional early stop
- `EventLogger` for automatic event logging without a separate pipeline
- listener registration events and per-dispatch observability counts as diagnostics, not a second routing layer

## Trade-Offs {#trade-offs}

The package keeps the common path simple, but it does not try to be a queue, transport layer, or retry system.

- **Benefit:** event flow stays easy to read and debug.
- **Benefit:** class-based matching keeps routing explicit.
- **Cost:** async delivery and retry policy stay outside the package.
- **Cost:** observability and business reactions need a small extra distinction.
