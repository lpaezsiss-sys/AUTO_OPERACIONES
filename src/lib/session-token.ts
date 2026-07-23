import { SignJWT, jwtVerify } from "jose";

export const SESSION_COOKIE = "inventario_session";
export const SESSION_DAYS = 7;

export type SessionPayload = {
  userId: string;
  username: string;
  name: string;
};

function getSecretKey() {
  const secret = process.env.AUTH_SECRET;
  if (!secret) {
    throw new Error("Falta AUTH_SECRET en variables de entorno");
  }
  return new TextEncoder().encode(secret);
}

export async function createSessionToken(
  payload: SessionPayload
): Promise<string> {
  return new SignJWT({ ...payload })
    .setProtectedHeader({ alg: "HS256" })
    .setIssuedAt()
    .setExpirationTime(`${SESSION_DAYS}d`)
    .sign(getSecretKey());
}

export async function verifySessionToken(
  token: string
): Promise<SessionPayload | null> {
  try {
    const { payload } = await jwtVerify(token, getSecretKey());
    const userId = payload.userId;
    const username = payload.username;
    if (typeof userId !== "string" || typeof username !== "string") {
      return null;
    }
    return {
      userId,
      username,
      name: typeof payload.name === "string" ? payload.name : "",
    };
  } catch {
    return null;
  }
}
