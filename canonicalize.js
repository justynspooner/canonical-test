import { readFileSync } from "fs";
import crypto from "crypto";
import canonicalize from "canonicalize";

// Get file path from command line argument
const filePath = process.argv[2];

if (!filePath) {
  console.error("Usage: node canonicalize.js <json-file>");
  process.exit(1);
}

try {
  // Read and parse JSON file
  const jsonContent = readFileSync(filePath, "utf-8");
  const data = JSON.parse(jsonContent);

  // Canonicalize the JSON
  const canonical = canonicalize(data);

  // Compute SHA256 hash
  const hash = crypto.createHash("sha256").update(canonical).digest("hex");

  // Output results
  console.log("=== JavaScript (canonicalize) ===");
  console.log("Canonical JSON:");
  console.log(canonical);
  console.log("");
  console.log("SHA256 Hash:");
  console.log(hash);
} catch (error) {
  console.error("Error:", error.message);
  process.exit(1);
}
