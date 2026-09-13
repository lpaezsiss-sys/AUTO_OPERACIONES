-- RedefineTables
PRAGMA defer_foreign_keys=ON;
PRAGMA foreign_keys=OFF;
CREATE TABLE "new_Product" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "code" TEXT NOT NULL,
    "name" TEXT NOT NULL,
    "description" TEXT NOT NULL DEFAULT '',
    "stock" REAL NOT NULL DEFAULT 0,
    "averageUnitCost" REAL NOT NULL DEFAULT 0,
    "lowStockThreshold" REAL NOT NULL DEFAULT 2,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL
);
INSERT INTO "new_Product" ("averageUnitCost", "code", "createdAt", "description", "id", "name", "stock", "updatedAt") SELECT "averageUnitCost", "code", "createdAt", "description", "id", "name", "stock", "updatedAt" FROM "Product";
DROP TABLE "Product";
ALTER TABLE "new_Product" RENAME TO "Product";
CREATE UNIQUE INDEX "Product_code_key" ON "Product"("code");
PRAGMA foreign_keys=ON;
PRAGMA defer_foreign_keys=OFF;
