CREATE TABLE user (id INTEGER PRIMARY KEY, user TEXT);
CREATE UNIQUE INDEX idx_user ON user(user);

CREATE TABLE page (id INTEGER PRIMARY KEY, page TEXT);
CREATE UNIQUE INDEX idx_page ON page(page);

CREATE TABLE project (id INTEGER PRIMARY KEY, page_id INTEGER, abbr TEXT, name TEXT);
CREATE UNIQUE INDEX idx_project ON project(page_id,abbr);

CREATE TABLE task (id INTEGER PRIMARY KEY, project_id INTEGER, abbr TEXT, name TEXT, active INTEGER);
CREATE UNIQUE INDEX idx_task ON task(project_id,abbr);

CREATE TABLE user_time (id INTEGER PRIMARY KEY, update_time INTEGER, user_id INTEGER, task_id INTEGER, `date` TEXT,  value INTEGER);
CREATE UNIQUE INDEX idx_user_time ON user_time(user_id,task_id,date);
