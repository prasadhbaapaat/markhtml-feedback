# MarkHTML Feedback System

A lightweight, PHP and SQLite-based feedback and review engine for Markdown documents. 

MarkHTML Feedback System allows you to take standard Markdown (`.md`) files, automatically split them into a paginated web document, and collect threaded feedback from users on every single section. It also includes a specialized **Questionnaire mode** to automatically generate inline answer forms for survey and Q&A documents. It is the perfect tool for reviewing documentation, technical specifications, API docs, surveys, or course materials.

## Features

- **Markdown Native**: Write your content in standard Markdown. The system parses and renders it beautifully.
- **Multiple Formats**: Support for standard section-level feedback, as well as a dynamic **Questionnaire mode** that turns Markdown ordered lists into interactive answer fields.
- **Multi-Document Support**: Host multiple different documents simultaneously on the same system.
- **Threaded Feedback**: Users can leave ratings and comments on specific sections, and reply to each other in threaded discussions.
- **Markdown Sync**: A powerful Admin feature that takes all database comments (or questionnaire answers) and injects them directly back into the original `.md` source files for offline reading and version control.
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

5. **First Login**:
   - Open the application in your browser.
   - Register a new account. Since you are the first user, you will need to manually grant yourself Admin access.
   - You can do this by opening `storage/database.sqlite` in an SQLite browser and setting `is_admin = 1` for your user row, OR by running the provided install script if one is included.

## How It Works

1. The system reads your Markdown file and looks for headers of a specific level (default `##`).
2. It splits the document into separate web pages based on those headers.
3. For standard documents, a feedback form is automatically generated at the bottom of the page. For `questionnaire` documents, inline answer fields are generated beneath every numbered list item.
4. When you click **Sync Comments** in the Admin panel, it writes the feedback back into your `.md` file. Section feedback is added at the bottom, while questionnaire answers are injected exactly below their respective questions. The parser automatically ignores these blocks when rendering the web view, preventing duplicate comments.

## License

This project is licensed under the GNU Affero General Public License v3.0 (AGPL-3.0) - see the [LICENSE](LICENSE) file for details.
