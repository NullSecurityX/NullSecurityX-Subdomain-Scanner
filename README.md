# NullSecurityX Subdomain Scanner

[![Demo Video](https://img.shields.io/badge/Demo-Video-brightgreen)](https://x.com/NullSecurityX/status/1982539191943852053)

A powerful DNS-based brute-force tool for discovering hidden subdomains fast. Built with PHP & Cloudflare API for parallel queries. Real-time UI, batch scanning, and easy setup!

## 🚀 Quick Demo

Watch the tool in action:

<video width="100%" height="auto" controls preload="metadata">
  <source src="https://video.twimg.com/amplify_video/1982538256110546944/vid/avc1/508x270/eFAS1l03Ty_E6lRX.mp4" type="video/mp4">
  Your browser does not support the video tag. [Watch on X](https://x.com/NullSecurityX/status/1982539191943852053)
</video>

## ✨ Features

- **Brute-Force Generation**: Custom charset (default: a-z0-9) with min/max length control.
- **Parallel DNS Queries**: cURL multi-handle for batches of 10 (configurable).
- **Real-Time UI**: JavaScript updates for live scanning progress, found/not found results.
- **Progress Bar & Status**: Track scans with percentage (limited mode) or unlimited counter.
- **Stop Button**: Graceful interruption mid-scan.
- **Export**: Results saved to `results.txt`.
- **Mobile-Responsive**: Clean, professional design with Orbitron/VT323 fonts.

## 📦 Installation

1. Clone the repo:
   ```
   git clone https://github.com/NullSecurityX/NullSecurityX-Subdomain-Scanner.git
   cd NullSecurityX-Subdomain-Scanner
   ```

2. Ensure PHP with cURL is installed (no other deps needed).

3. Host `sub.php` on a web server (e.g., Apache/Nginx with PHP).

## 🚀 Usage

1. Open `index.php` in your browser.
2. Enter the **Domain** (e.g., `example.com`).
3. Customize **Charset** (default: `abcdefghijklmnopqrstuvwxyz0123456789`).
4. Set **Min Len** (default: 1) and **Max Len** (default: 2).
5. Optional: **Limit** (0 for unlimited).
6. Click **Start Scan** – watch results populate live!
7. Use **Stop Scan** to halt anytime.
8. Check `results.txt` for exported found subdomains.

### Example Scan
- Domain: `tesla.com`
- Charset: `abcdefghijklmnopqrstuvwxyz`
- Min/Max Len: 1-3
- Limit: 1000

Results show in two columns: Attempts (cyan) and Found (yellow).

## ⚠️ Ethical Use
- For authorized security testing only (pentest, bug bounty).
- Respect rate limits; Cloudflare API may throttle heavy use.
- Do not scan without permission.

## 🤝 Contributing
Pull requests welcome! For major changes, open an issue first.

## 📄 License
MIT License – see [LICENSE](LICENSE) file.

## 👥 Credits
- Developed by [@NullSecurityX](https://x.com/NullSecurityX)
- Follow for more tools! 🌟

---

⭐ **Star the repo if you found it useful!** Questions? [Open an issue](https://github.com/NullSecurityX/NullSecurityX-Subdomain-Scanner/issues).
