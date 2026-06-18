import { defineConfig } from "@sugarcube-sh/cli";

export default defineConfig({
	resolver: "tokens/tokens.resolver.json",
	variables: {
		path: ".generated/sugarcube.css",
		prefix: "emilias",
		transforms: {
			colorFallbackStrategy: "native",
			fluid: {
				min: 320,
				max: 1200,
			},
		},
	},
});
