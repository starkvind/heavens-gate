# Biography Forms implementation contract

Presentation behavior is intentionally independent from the existence of a base row in `dim_forms`.

The biography view must synthesize the base state as `Homínido`, preserve all original Attribute values, and apply form modifiers additively to the stored character Attributes. Final displayed transformed values may be zero but never negative.
