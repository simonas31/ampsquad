import { DenseLayout } from "@/components/shop/designs/DenseLayout";
import { PortalLayout } from "@/components/shop/designs/PortalLayout";
import { TilesLayout } from "@/components/shop/designs/TilesLayout";
import type { ShopListingProps } from "@/components/shop/designs/types";
import {
  DesignSwitcher,
  useShopDesign,
} from "@/components/shop/DesignSwitcher";

const layouts = {
  a: PortalLayout,
  b: TilesLayout,
  c: DenseLayout,
};

export default function Index(props: ShopListingProps) {
  const [design, setDesign] = useShopDesign();
  const Layout = layouts[design];

  return (
    <>
      <Layout {...props} />
      <DesignSwitcher design={design} onChange={setDesign} />
    </>
  );
}
