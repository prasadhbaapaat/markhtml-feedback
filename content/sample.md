# Welcome to MarkHTML Feedback System

The Content Below the first H1 tag will be shown as Overview page content.

This sample document is designed to test all features supported by the MarkHTML Feedback Markdown parser to ensure edge cases are not missed.

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer sit amet nunc arcu. Ut molestie ac leo at porta. Ut eu sagittis risus. Fusce leo tellus, auctor eu elit a, pulvinar mattis est. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Nunc fermentum placerat dignissim. Aenean lacus nibh, feugiat sit amet commodo eget, feugiat at purus. Cras justo nunc, congue vel nisl vel, semper posuere lacus. Praesent neque erat, ultrices vitae viverra sit amet, mattis vel dui. Pellentesque venenatis, eros in efficitur efficitur, risus augue fermentum dui, commodo mollis urna nunc id mi. Nunc interdum porttitor leo, at facilisis lorem pellentesque ac. Vivamus et ex et quam imperdiet ornare sit amet eget elit. Donec velit sapien, sagittis sit amet nisi eget, tempus luctus tortor. Mauris id enim in lorem dictum vulputate ut fermentum neque. Donec rhoncus neque ut consectetur tristique.

## Level 2 Heading

The system will split your document whenever it encounters a Level 2 Heading (like the one above).

Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Donec vehicula, nunc nec tristique blandit, neque nunc fringilla est, iaculis luctus odio augue vel dui. Etiam placerat purus vehicula neque feugiat, a rhoncus elit fringilla. Nam eu justo at velit sodales pulvinar vel vel nulla. Vestibulum vulputate et metus non aliquam. Cras tincidunt urna ut tincidunt interdum. Mauris efficitur tincidunt dolor luctus maximus. Pellentesque pharetra purus ut maximus pretium. Proin consectetur porta interdum. Sed posuere dolor vitae dolor ultrices, eget aliquet ante faucibus.

Users can read this section and leave feedback at the bottom.

## Typography and Styling

You can use **bold text**, *italic text*, and ***bold italic text***.
You can also use __bold text with underscores__ and _italic text with underscores_.
Here is a test for the bug we just fixed: `inline code 1` and `inline code 2` on the same line!

Some people also use ~~strikethrough~~ (if supported, though our parser might just leave it as is if not implemented). Let's test [Underlined Text]{.underline} too.

## Blockquotes

> This is a blockquote.
> It can span multiple lines.
> > It can even be nested!

## Lists

### Unordered Lists
* Item 1
* Item 2
  * Sub-item A
  * Sub-item B
    * Deeply nested item
* Item 3

### Ordered Lists
1. First step
2. Second step
   1. Sub-step 2.1
   2. Sub-step 2.2
3. Third step

## Links and Images

Here is a [link to Google](https://google.com).
Here is a link with a title: [Hover me!](https://github.com "This is a title").

Here is an image:
![Placeholder Image](https://picsum.photos/150 "Placeholder Title")

## Code Blocks

Here is a fenced code block:

```php
function testParser() {
    echo "Hello World!";
    return true;
}
```

## Tables

| Header 1 | Header 2 | Header 3 |
| -------- | :------: | -------: |
| Left     |  Center  |    Right |
| Row 2    |   Data   |     1234 |

## Alignment Blocks

:: This text should be centered ::

This text should be right aligned ::

:: This text should be left aligned

## Edge Cases

- A list item with a `code block` inside it.
- **Bold text that contains `inline code` inside it.**
- `Inline code that contains **asterisks** inside it.`

> A blockquote that contains a list:
> - Item A
> - Item B
>
> And a table:
> | Col 1 | Col 2 |
> | ----- | ----- |
> | Val 1 | Val 2 |


## Survey Form Example

This section demonstrates the system's ability to map a specific Markdown section to a completely custom PHP form using the `special_forms` array in `config.php`.

Below this text, instead of the standard threaded comment box, you will see a detailed, multi-question survey form that was rendered from `questionnaire_form.php`.

## How to Configure

1. Copy `includes/config.example.php` to `includes/config.php`.
2. Open `config.php` and configure your settings.
3. Replace this file with your actual content.