-- Fix boundaries uniqueness so multiple selectable items in same category can be saved.
ALTER TABLE profile_boundaries
  DROP INDEX uq_profile_boundary,
  ADD UNIQUE KEY uq_profile_boundary (user_id, boundary_key, boundary_value);
