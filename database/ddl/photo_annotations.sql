CREATE TABLE photo_annotations (
  id integer primary key,
  checklist_id integer not null,
  label varchar(150) not null,
  metadata jsonb,
  created_at timestamp,
  updated_at timestamp
);