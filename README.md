# Image Watermark #

Image Watermark allows you to automatically watermark images uploaded to the WordPress Media Library and bulk watermark previously uploaded images.

## Description ##

[Image Watermark](http://www.dfactory.co/products/image-watermark/) allows you to automatically watermark images uploaded to the WordPress Media Library and bulk watermark previously uploaded images.

For more information, check out the plugin page at [dFactory](http://www.dfactory.co/), the [documentation page](https://www.dfactory.co/docs/image-watermark/), or the plugin [support forum](http://www.dfactory.co/support/forum/image-watermark/).

### Features include: ###

* Bulk watermark - Apply watermark option in Media Library actions
* Watermark images already uploaded to Media Library
* GD library and ImageMagick support
* Choose the position of the watermark image
* Upload a custom watermark image
* Watermark image preview
* Set watermark offset
* Select post types where the watermark will be applied to images, or apply the watermark during any image upload
* Choose from three watermark size modes: original, custom or scaled
* Set watermark transparency / opacity
* Select image format (baseline or progressive)
* Set image quality
* Protect your images from copying via drag and drop
* Disable right-click on images
* Disable image protection for logged-in users
* .pot file for translations included

## Installation ##

1. Install Image Watermark either via the WordPress.org plugin directory, or by uploading the files to your server
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to the Watermark menu in Settings and set your watermarking options.
4. Enable watermark to apply watermark to uploaded images or go to Media Library to apply watermark to previously uploaded images

## Development (Vite build) ##

* Sources live in `src/js` and `src/scss`; built files output to `js/*.js` and `css/image-watermark.css` with the same names the plugin enqueues. The legacy `wp-like-ui-theme.css` asset and its images were removed.
* Install deps with `npm install`, then run `npm run build` to regenerate the distributed JS and CSS in-place (no hashes, no manifest). Use `npm run watch` for a rebuild-on-change loop.
* All admin/front JS was rewritten to vanilla ES6 and builds target ES5 for compatibility; WordPress no longer needs jQuery/jquery-ui as dependencies for these scripts.
* `npm run dev` and `npm run preview` are available for local iteration if needed. Node 18+ is recommended.
