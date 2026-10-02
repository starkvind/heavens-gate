# Biography form presentation notes

The biography Forms tab treats the base form as a virtual presentation state rather than a `dim_forms` row.

Current rules:

- Base label: `Homínido`.
- Up to five form states should fit on one desktop row when space permits.
- The Forms detail panel renders the character's complete Attribute line.
- Form modifiers may reduce transformed Attributes to `0`; the presentation layer must not clamp them to `1`.
- Missing silhouette images keep an empty visual slot and do not fall back to `image_url`.
- Bastet rows may share silhouette image paths between breeds when the form silhouette is the same.
