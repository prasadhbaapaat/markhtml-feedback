# MarkHTML Feedback System

A lightweight, PHP and SQLite-based feedback and review engine for Markdown documents. 

MarkHTML Feedback System allows you to take standard Markdown (`.md`) files, automatically split them into a paginated web document, and collect threaded feedback from users on every single section. It also includes a specialized **Questionnaire mode** to automatically generate inline answer forms for survey and Q&A documents. It is the perfect tool for reviewing documentation, technical specifications, API docs, surveys, or course materials.

## Features

- **Markdown Native**: Write your content in standard Markdown. The system parses and renders it beautifully.
- **Multiple Formats**: Support for standard section-level feedback, as well as a dynamic **Questionnaire mode** that turns Markdown ordered lists into interactive answer fields.
- **File Uploads**: Users can upload files (images, documents, spreadsheets) directly into their comments using the `[UPLOAD]` or `[UPLOAD, 'Custom Title']` shortcodes.
- **Multi-Document Support**: Host multiple different documents simultaneously on the same system.
- **Threaded Feedback**: Users can leave ratings and comments on specific sections, and reply to each other in threaded discussions.
- **Markdown Sync**: A powerful Admin feature that takes all database comments (or questionnaire answers and uploaded file links) and injects them directly back into the original `.md` source files for offline reading and version control.
- **Admin Dashboard**: Manage users, clear the HTML render cache, wipe comments, and trigger Markdown syncs.
- **Lightweight & Fast**: Powered by SQLite and vanilla PHP. No complex databases or Node.js build steps required.

## Installation

1. **Clone the repository** to your web server (e.g., Apache/Nginx with PHP 8.0+):
   ```bash
   git clone https://github.com/prasadhbaapaat/markhtml-feedback.git
   cd markhtml-feedback
   ```

2. **Configure the system**:
   - Copy the example configuration file:
     ```bash
     cp includes/config.example.php includes/config.php
     ```
   - Open `includes/config.php` and set your preferred site title, admin email, and define the paths to your Markdown documents.

3. **Set Permissions**:
   - Ensure the `storage/` directory is writable by your web server. This is where the SQLite database and cached HTML files will be created.
   ```bash
   chmod -R 775 storage/
   ```

4. **Add your Content**:
   - Drop your `.md` files into the `content/` directory.
   - Update your `config.php` to point to the new files.

5. **Admin Setup**:
   - Out of the box, your copied `config.php` contains a `default_users` array.
   - Simply configure your initial Admin email and password in that array.
   - When you load the site or attempt to log in, this admin user will be automatically created in the database for you.

## How It Works

1. The system reads your Markdown file and looks for headers of a specific level (default `##`).
2. It splits the document into separate web pages based on those headers.
3. For standard documents, a feedback form is automatically generated at the bottom of the page. For `questionnaire` documents, inline answer fields are generated beneath every numbered list item.
4. When you click **Sync Comments** in the Admin panel, it writes the feedback back into your `.md` file. Section feedback is added at the bottom, while questionnaire answers are injected exactly below their respective questions. The parser automatically ignores these blocks when rendering the web view, preventing duplicate comments.
5. On the first sync, each questionnaire question is also tagged with a hidden stable ID (e.g. `<!-- qid:4f3a2b1c -->`). Answers stay linked to that ID, so collected answers survive even if you later reword a question, and identically-worded questions never get their answers mixed up.

## License

This project is licensed under the GNU Affero General Public License v3.0 (AGPL-3.0) - see the [LICENSE](LICENSE) file for details.
