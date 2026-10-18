# MEMOVIA (local_memovia)

Moodle local plugin for [MEMOVIA](https://memovia.io), the service that makes course materials accessible to the
whole class (read aloud, adapted reading) instead of giving a separate tool to a few learners.

In every course that contains a MEMOVIA activity (an LTI 1.3 external tool registered on the site), the plugin:

- sends the PDF, DOCX and PPTX files of File and Folder resources **visible to everyone** to MEMOVIA, which adapts them and shares them with the class;
- adds an **Open with MEMOVIA** button next to each adapted file;
- notifies learners through Moodle messaging when their teacher shares new MEMOVIA content with the class.

## What is sent, and what never is

- Only files of a visible course, in a visible activity shown on the course page, with no access restriction on the
  activity or its section. Hidden or restricted files and images are never sent.
- The course full name and resource names are sent with the files, to name them in MEMOVIA.
- When a user opens a file with MEMOVIA, the plugin sends their Moodle user id to MEMOVIA so that their next LTI
  launch opens the right file.
- Names, roles and course membership reach MEMOVIA through the LTI 1.3 services of the External tool activity
  (`mod_lti`), as for any LTI tool.
- Data is processed in the EU. MEMOVIA extracts the text with Microsoft Azure Document Intelligence and reads it aloud
  with Microsoft Azure AI Speech (EU regions). See the Privacy API metadata of the plugin.

## Requirements

- Moodle 4.5 (LTS) or 5.0.
- A MEMOVIA subscription for the institution. The plugin does nothing on its own: it connects Moodle to the MEMOVIA
  service, which is paid for per institution.
- The MEMOVIA external tool registered on the site with LTI 1.3 dynamic registration (the registration URL is given
  by MEMOVIA to the institution).

The plugin ships with English strings only; translations are managed on [AMOS](https://lang.moodle.org).

## Installation

1. Download the latest release zip from the
   [releases page](https://github.com/Naou95/moodle-local_memovia/releases) and install it from
   *Site administration > Plugins > Install plugins*, or copy the folder to `local/memovia`.
2. Go to *Site administration > Plugins > Local plugins > MEMOVIA*.
3. Check that **MEMOVIA address** is the address of the registered MEMOVIA tool (default `https://app.memovia.io`).
4. Tick **Send course files to MEMOVIA**. It is off by default; untick it to stop all sending (for example at the
   end of a contract).
5. In each course, add the MEMOVIA external tool activity (visible) and open it once: this creates the
   class in MEMOVIA, and files are sent from then on.

Authentication: every call is signed with the site's LTI key (the `mod_lti` key); MEMOVIA checks it with the public
key received at registration. There is no secret to type.

The scheduled task `\local_memovia\task\sync_files` runs every 5 minutes.

## Tests

```
vendor/bin/phpunit --testsuite local_memovia_testsuite
```

## Support

Bugs and questions: [GitHub issues](https://github.com/Naou95/moodle-local_memovia/issues).

## License

2026 MEMOVIA SAS. GNU GPL v3 or later, see [LICENSE](LICENSE).
