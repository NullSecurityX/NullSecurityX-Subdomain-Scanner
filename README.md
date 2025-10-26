### NullSecurityX Subdomain Scanner

NullSecurityX Subdomain Scanner is a DNS-based subdomain discovery tool. This tool scans potential subdomains for a specified domain using brute-force methods and quickly generates results through parallel queries via the Cloudflare DNS API.

#### Key Features:
- **Brute-Force Generation**: Produces all possible combinations within the specified character set (charset) and minimum/maximum length range (e.g., 1-2 character subdomains using a-z and 0-9 characters).
- **Parallel DNS Queries**: Uses cURL multi-handle to perform queries in batches of 30, increasing scan speed.
- **Real-Time Visual Interface**: Provides dynamic updates during scanning via JavaScript; attempted subdomains are highlighted in blue, and found ones in green. Results are saved to a text file (results.txt).
- **Security and Limitations**: You can limit the number of scans with the limit parameter, or run in unlimited mode (0). Memory and time limits are optimized in PHP.
- **User Interface**: Features a modern gradient background, integrated rounded logo (from NullSecurityX's X profile), hover effects, and a mobile-responsive design for visual appeal.

#### Usage:
1. Enter the domain (e.g., example.com).
2. Customize the charset (default: a-z0-9).
3. Set min/max length and limit.
4. Click "Start Scan".

This tool is ideal for security testing (pentest) or domain discovery, but ensure legal and ethical use—only run it on your own domains or with permission. Scan results are displayed live in the browser and logged to a file.
