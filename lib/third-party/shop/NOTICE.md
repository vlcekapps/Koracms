# Shop document dependencies

Production PHP requires only PHP 8.0+, mbstring and zlib. No Composer autoloader,
database, external service, HTML parser or executable is used by the renderer.

## QR Code generator

`QrCode.php` is a PHP translation of Project Nayuki's official Python QR Code
generator v1.8.0, not a WordPress plugin or the unrelated KrivArt PHP port.
Copyright (c) Project Nayuki, MIT license in `LICENSE-Nayuki.txt`.
Upstream: https://github.com/nayuki/QR-Code-generator/tree/v1.8.0
`qrcodegen.py` is a test oracle only; production never runs Python.
Only trailing whitespace was removed, with LF line endings preserved; no
functional code was changed. The original upstream SHA-256 is
`b089855caf16185c61421ea4927c1b213cf9468940d71fa8ab11ef83662dcc84`.
The bundled whitespace-normalized SHA-256 is
`d9ac5943cb22fbd8a72e2ad846ecb055a1484ab74d8af8d118071eaeb684affb`.
The PHP translation supports all 40 Model 2 versions, four ECC levels, eight
masks, numeric/alphanumeric/byte/custom Kanji segments and ECI. Its default
mask selection and matrices are compared with the official upstream by tests.

## Font

`DejaVuSans.ttf` is the unmodified DejaVu Fonts 2.37 release, embedded in full.
Upstream: https://dejavu-fonts.github.io/
Release: https://github.com/dejavu-fonts/dejavu-fonts/releases/tag/version_2_37
The release's complete copyright/license is preserved in `LICENSE-DejaVu.txt`.
Only trailing whitespace on one license line was removed for repository lint.
Font SHA-256: `7da195a74c55bef988d0d48f9508bd5d849425c1770dba5d7bfc6ce9ed848954`.
Bitstream Vera copyright applies; DejaVu changes are public domain and imported
Arev glyphs retain their copyright. The font is not sold separately.

`verify.py` is optional development-only PDF/QR verification using pypdf/Pillow
and, when installed, zxing-cpp. No Python packages are production dependencies.
